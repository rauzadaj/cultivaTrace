/**
 * apps/frontend/src/services/api.ts
 *
 * Service HTTP centralisé.
 * TOUS les appels API passent par ici — jamais d'Axios direct dans les composants.
 *
 * Le JWT est injecté automatiquement via l'intercepteur.
 * Un 401 déclenche le logout automatique.
 */

import axios, { type AxiosInstance } from 'axios'
import type {
  HydraCollection,
  Plant, PlantEvent, Farm, Room, Strain,
  InputRecord, HarvestRecord, Sensor, SensorHistoryResponse,
  User, Organization, JwtResponse, LoginCredentials, ApiError,
} from '@/types/api'

const TOKEN_KEY = 'cultivatrace_token'
const USER_EMAIL_KEY = 'cultivatrace_user_email'

function resolveApiBaseUrl(): string {
  const configuredBaseUrl = (import.meta.env.VITE_API_URL as string | undefined)?.trim()

  if (!configuredBaseUrl) {
    return '/api'
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

// Injecteur JWT — ajoute le token sur chaque requête
http.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY)
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Handler 401 — logout automatique
http.interceptors.response.use(
  (response) => response,
  async (error) => {
    if (error.response?.status === 401) {
      localStorage.removeItem(TOKEN_KEY)
      localStorage.removeItem('refresh_token')
      localStorage.removeItem(USER_EMAIL_KEY)
      // Redirection vers login sans import circulaire
      window.location.href = '/auth'
    }
    return Promise.reject(error)
  }
)

// ── Auth ──────────────────────────────────────────────────────────────────

export const authApi = {
  login: (credentials: LoginCredentials) =>
    http.post<JwtResponse>('/auth/login', credentials, {
      headers: { 'Content-Type': 'application/json' },
    }),

  refresh: (refreshToken: string) =>
    http.post<JwtResponse>('/auth/token/refresh', { refresh_token: refreshToken }, {
      headers: { 'Content-Type': 'application/json' },
    }),

  me: () =>
    http.get<User>('/me'),
}

export const kybApi = {
  upload: (formData: FormData) =>
    http.post('/kyb/upload', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    }),

  status: () =>
    http.get('/kyb/status'),
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
}

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

// ── Export par défaut ─────────────────────────────────────────────────────

export default http
