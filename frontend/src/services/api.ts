/**
 * apps/frontend/src/services/api.ts
 *
 * Centralized HTTP service.
 * ALL API calls go through here — never use Axios directly in components.
 *
 * The JWT is injected automatically via the interceptor.
 * A 401 first attempts a silent refresh token rotation.
 */

import axios, { type AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'
import type {
  HydraCollection,
  Plant, PlantEvent, Farm, Room, Strain,
  InputRecord, HarvestRecord, Sensor, SensorHistoryResponse,
  User, Organization, JwtResponse, LoginCredentials, ApiError, DashboardOverviewResponse,
  OrganizationRegistrationPayload, RegistrationResponse,
  OrganizationSettingsResponse, OrganizationMember, OrganizationInvitation,
  PersistentAlert,
  ReportExport,
  KybStatusResponse,
  KybSubmitResponse,
} from '@/types/api'
import { clearAuthTokens, getAccessToken, getRefreshToken, redirectToAuth, setAuthTokens, signalSessionExpired } from './authSession'

function resolveRailwayApiBaseUrl(): string | null {
  if (typeof window === 'undefined') {
    return null
  }

  const { hostname, origin } = window.location

  if (!hostname.endsWith('.up.railway.app')) {
    return null
  }

  if (hostname.startsWith('api-')) {
    return `${origin}/api`
  }

  return `${window.location.protocol}//api-${hostname}/api`
}

function resolveApiBaseUrl(): string {
  const configuredBaseUrl = (import.meta.env.VITE_API_URL as string | undefined)?.trim()

  if (!configuredBaseUrl) {
    return resolveRailwayApiBaseUrl() ?? '/api'
  }

  const normalizedBaseUrl = configuredBaseUrl.replace(/\/+$/, '')

  return normalizedBaseUrl.endsWith('/api')
    ? normalizedBaseUrl
    : `${normalizedBaseUrl}/api`
}

// ── Instance Axios ────────────────────────────────────────────────────────

const http: AxiosInstance = axios.create({
  baseURL: resolveApiBaseUrl(),
  headers: {
    'Content-Type': 'application/ld+json',
    'Accept': 'application/ld+json',
  },
})

const refreshHttp: AxiosInstance = axios.create({
  baseURL: resolveApiBaseUrl(),
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

let refreshPromise: Promise<string | null> | null = null

interface RetryableRequestConfig extends InternalAxiosRequestConfig {
  _retry?: boolean
}

// JWT injector — adds the token to every request
http.interceptors.request.use((config) => {
  const token = getAccessToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

async function refreshAccessToken(): Promise<string | null> {
  const refreshToken = getRefreshToken()

  if (!refreshToken) {
    return null
  }

  if (!refreshPromise) {
    refreshPromise = refreshHttp
      .post<JwtResponse>('/auth/token/refresh', { refreshToken })
      .then(({ data }) => {
        setAuthTokens({
          token: data.token,
          refreshToken: data.refreshToken,
        })

        return data.token
      })
      .catch(() => {
        clearAuthTokens()
        return null
      })
      .finally(() => {
        refreshPromise = null
      })
  }

  return refreshPromise
}

function shouldAttemptRefresh(config?: RetryableRequestConfig): boolean {
  if (!config || config._retry) {
    return false
  }

  const requestUrl = config.url ?? ''

  return !requestUrl.includes('/auth/login') && !requestUrl.includes('/auth/token/refresh')
}

// Handler 401 — tentative de refresh avant logout
http.interceptors.response.use(
  (response) => response,
  async (error) => {
    const axiosError = error as AxiosError
    const originalRequest = axiosError.config as RetryableRequestConfig | undefined

    if (axiosError.response?.status === 401 && shouldAttemptRefresh(originalRequest)) {
      originalRequest._retry = true

      const refreshedAccessToken = await refreshAccessToken()

      if (refreshedAccessToken) {
        originalRequest.headers = originalRequest.headers ?? {}
        originalRequest.headers.Authorization = `Bearer ${refreshedAccessToken}`

        return http.request(originalRequest)
      }

      // Refresh failed — session truly expired
      signalSessionExpired()
      redirectToAuth()
    } else if (axiosError.response?.status === 401) {
      clearAuthTokens()
      signalSessionExpired()
      redirectToAuth()
    }

    return Promise.reject(axiosError)
  }
)

// ── Auth ──────────────────────────────────────────────────────────────────

export const authApi = {
  login: (credentials: LoginCredentials) =>
    http.post<JwtResponse>('/auth/login', credentials, {
      headers: { 'Content-Type': 'application/json' },
    }),

  registerOrganization: (payload: OrganizationRegistrationPayload) =>
    http.post<RegistrationResponse>('/register/organization', payload, {
      headers: { 'Content-Type': 'application/json' },
    }),

  acceptInvitation: (token: string, password: string) =>
    http.post<{ message: string; email: string }>('/register/invitation/accept', { token, password }, {
      headers: { 'Content-Type': 'application/json' },
    }),

  refresh: (refreshToken: string) =>
    refreshHttp.post<JwtResponse>('/auth/token/refresh', { refreshToken }),

  me: () =>
    http.get<User>('/me'),

  logout: (bearerToken?: string) =>
    http.post('/auth/logout', null, {
      headers: {
        'Content-Type': 'application/json',
        // Explicit token needed when called after clearAuthTokens() clears storage
        ...(bearerToken ? { Authorization: `Bearer ${bearerToken}` } : {}),
      },
    }),
}

export const kybApi = {
  upload: (formData: FormData) =>
    http.post<KybSubmitResponse>('/kyb/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  status: () =>
    http.get<KybStatusResponse>('/kyb/status'),
}

export const billingApi = {
  checkout: (plan: string) =>
    http.post('/billing/checkout', { plan }, {
      headers: { 'Content-Type': 'application/json' },
    }),

  confirmCheckout: (sessionId: string) =>
    http.post('/billing/checkout/confirm', { sessionId }, {
      headers: { 'Content-Type': 'application/json' },
    }),

  portal: () =>
    http.post('/billing/portal'),

  status: () =>
    http.get('/billing/status'),

  contactSales: (payload: { name: string; email: string; company: string; message: string }) =>
    http.post('/contact/sales', payload, {
      headers: { 'Content-Type': 'application/json' },
    }),
}

export const organizationAdminApi = {
  settings: () =>
    http.get<OrganizationSettingsResponse>('/organization/settings'),

  updateSettings: (payload: Partial<Pick<OrganizationSettingsResponse, 'name' | 'contactEmail' | 'config'>>) =>
    http.patch<OrganizationSettingsResponse>('/organization/settings', payload, {
      headers: { 'Content-Type': 'application/json' },
    }),

  members: () =>
    http.get<{ members: OrganizationMember[] }>('/organization/members'),

  invitations: () =>
    http.get<{ invitations: OrganizationInvitation[] }>('/organization/invitations'),

  invite: (payload: { email: string; role: 'ROLE_ORG_ADMIN' | 'ROLE_ORG_USER' }) =>
    http.post<OrganizationInvitation>('/organization/invitations', payload, {
      headers: { 'Content-Type': 'application/json' },
    }),
}

export const dashboardApi = {
  overview: () =>
    http.get<DashboardOverviewResponse>('/dashboard'),
}

export const alertsApi = {
  list: () =>
    http.get<HydraCollection<PersistentAlert>>('/alerts', {
      params: { itemsPerPage: 20 },
    }),

  acknowledge: (id: string) =>
    http.post<PersistentAlert>(`/alerts/${id}/acknowledge`, {}, {
      headers: { 'Content-Type': 'application/json' },
    }),
}

export const reportingApi = {
  list: () =>
    http.get<HydraCollection<ReportExport>>('/reporting/exports', {
      params: { itemsPerPage: 20 },
    }),

  createHarvestSummary: (payload: { dateFrom: string; dateTo: string; farmId?: string | null; roomId?: string | null }) =>
    http.post<ReportExport>('/reporting/harvest-summary', payload, {
      headers: { 'Content-Type': 'application/json' },
    }),

  createAuditExport: (payload: { dateFrom: string; dateTo: string; format: 'pdf' | 'csv' }) =>
    http.post<ReportExport>('/reporting/audit-export', payload, {
      headers: { 'Content-Type': 'application/json' },
    }),

  download: async (downloadUrl: string, fileName: string): Promise<void> => {
    const response = await http.get(downloadUrl.replace(/^\/api/, ''), { responseType: 'blob' })
    const url = window.URL.createObjectURL(response.data)
    const a = document.createElement('a')
    a.href = url
    a.download = fileName
    a.click()
    window.URL.revokeObjectURL(url)
  },
}

export { refreshAccessToken }

// ── Plants ────────────────────────────────────────────────────────────────

export const plantsApi = {
  list: (params?: Record<string, unknown>) =>
    http.get<HydraCollection<Plant>>('/plants', { params }),

  get: (id: string) =>
    http.get<Plant>(`/plants/${id}`),

  create: (data: Partial<Plant>) =>
    http.post<Plant>('/plants', data),

  update: (id: string, data: Partial<Pick<Plant, 'stage' | 'room' | 'rfidTag'>>) =>
    http.patch<Plant>(`/plants/${id}`, data, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    }),

  harvest: (id: string, data: { grossWeightG: number; netWeightG: number; notes?: string; harvestedAt: string }) =>
    http.post<HarvestRecord>(`/plants/${id}/harvest`, data, {
      headers: { 'Content-Type': 'application/json' },
    }),

  downloadReport: async (id: string): Promise<void> => {
    const response = await http.get(`/plants/${id}/report`, { responseType: 'blob' })
    const url = URL.createObjectURL(new Blob([response.data], { type: 'application/pdf' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `rapport-plant-${id}.pdf`
    a.click()
    URL.revokeObjectURL(url)
  },

  initiateDestruction: (id: string, data: { reason: string }) =>
    http.post(`/plants/${id}/destroy`, data, {
      headers: { 'Content-Type': 'application/json' },
    }),
}

// ── Plant Events ──────────────────────────────────────────────────────────

export const plantEventsApi = {
  list: (plantId: string, params?: Record<string, unknown>) =>
    http.get<HydraCollection<PlantEvent>>('/plant_events', {
      params: {
        'plant.id': plantId,
        'order[occurredAt]': 'desc',
        ...params,
      },
    }),

  listAll: (params?: Record<string, unknown>) =>
    http.get<HydraCollection<PlantEvent>>('/plant_events', {
      params: {
        'order[occurredAt]': 'desc',
        ...params,
      },
    }),
}


// ── Farms ────────────────────────────────────────────────────────────────

export const farmsApi = {
  list: (params?: Record<string, unknown>) =>
    http.get<HydraCollection<Farm>>('/farms', { params }),

  create: (data: Partial<Farm>) =>
    http.post<Farm>('/farms', data),
}

// ── Rooms ─────────────────────────────────────────────────────────────────

export const roomsApi = {
  list: (params?: Record<string, unknown>) =>
    http.get<HydraCollection<Room>>('/rooms', { params }),

  get: (id: string) =>
    http.get<Room>(`/rooms/${id}`),

  create: (data: Partial<Room>) =>
    http.post<Room>('/rooms', data),

  update: (id: string, data: Partial<Room>) =>
    http.patch<Room>(`/rooms/${id}`, data, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    }),
}

// ── Strains ───────────────────────────────────────────────────────────────

export const strainsApi = {
  list: (params?: Record<string, unknown>) =>
    http.get<HydraCollection<Strain>>('/strains', { params }),

  create: (data: Partial<Strain>) =>
    http.post<Strain>('/strains', data),
}

// ── Sensors ───────────────────────────────────────────────────────────────

export const sensorsApi = {
  list: (params?: Record<string, unknown>) =>
    http.get<HydraCollection<Sensor>>('/sensors', { params }),

  get: (id: string) =>
    http.get<Sensor>(`/sensors/${id}`),

  create: (data: Partial<Sensor>) =>
    http.post<Sensor>('/sensors', data),

  update: (id: string, data: Partial<Sensor>) =>
    http.patch<Sensor>(`/sensors/${id}`, data, {
      headers: { 'Content-Type': 'application/merge-patch+json' },
    }),

  readings: (id: string, period: '7d' | '30d' | '90d' | '365d') =>
    http.get<SensorHistoryResponse>(`/sensors/${id}/readings`, { params: { period } }),
}

// ── Compliance ────────────────────────────────────────────────────────────

export const complianceApi = {
  ctsReport: async (month: string): Promise<void> => {
    // month format: YYYY-MM
    const response = await http.get('/compliance/ctsreport', {
      params: { month },
      responseType: 'blob',
    })
    const url = URL.createObjectURL(new Blob([response.data], { type: 'text/csv' }))
    const a = document.createElement('a')
    a.href = url
    a.download = `cts-report-${month}.csv`
    a.click()
    URL.revokeObjectURL(url)
  },

  verifyAuditTrail: (plantId: string) =>
    http.get<{ valid: boolean; brokenAt: string | null; checked: number }>('/audit/verify', {
      params: { plantId },
    }),
}

// ── Default export ────────────────────────────────────────────────────────

export default http
