import type { AnalyticsResponse, AuthTokenResponse, HydraCollection, RegistrationResponse } from '../types/api'
import { useUserStore } from '../stores/useUserStore'

export class ApiError extends Error {
  constructor(
    message: string,
    public readonly status: number,
  ) {
    super(message)
  }
}

interface ApiFetchOptions {
  accept?: string
  authenticate?: boolean
  contentType?: string
  redirectOnUnauthorized?: boolean
}

function redirectToAuth() {
  if (window.location.pathname.startsWith('/auth')) {
    return
  }

  const redirect = encodeURIComponent(`${window.location.pathname}${window.location.search}`)
  window.location.assign(`/auth?redirect=${redirect}`)
}

async function parseError(response: Response): Promise<string> {
  try {
    const payload = await response.json()

    return payload.detail ?? payload['hydra:description'] ?? payload.message ?? `HTTP ${response.status}`
  } catch {
    return `HTTP ${response.status}`
  }
}

export async function apiFetch<T>(
  path: string,
  init: RequestInit = {},
  options: ApiFetchOptions = {},
): Promise<T> {
  const userStore = useUserStore()
  const baseUrl = userStore.normalizedApiBase
  const url = `${baseUrl}${path.startsWith('/') ? path : `/${path}`}`
  const headers = new Headers(init.headers)
  const authenticate = options.authenticate ?? true
  const redirectOnUnauthorized = options.redirectOnUnauthorized ?? true

  headers.set('Accept', options.accept ?? 'application/ld+json, application/json')

  if (init.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', options.contentType ?? 'application/ld+json')
  }

  if (authenticate && userStore.token) {
    headers.set('Authorization', `Bearer ${userStore.token}`)
  }

  const response = await fetch(url, { ...init, headers })

  if (!response.ok) {
    const message = await parseError(response)

    if (response.status === 401 && redirectOnUnauthorized) {
      userStore.clearSession()
      redirectToAuth()
    }

    throw new ApiError(message, response.status)
  }

  if (response.status === 204) {
    return null as T
  }

  return (await response.json()) as T
}

export async function loadHydraCollection<T>(path: string): Promise<T[]> {
  const payload = await apiFetch<HydraCollection<T>>(path)

  return payload['hydra:member'] ?? []
}

export async function loadAnalytics(path: string): Promise<AnalyticsResponse> {
  return apiFetch<AnalyticsResponse>(path, {}, { accept: 'application/json' })
}

export async function login(email: string, password: string): Promise<AuthTokenResponse> {
  return apiFetch<AuthTokenResponse>(
    '/login',
    {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    },
    {
      accept: 'application/json',
      authenticate: false,
      contentType: 'application/json',
      redirectOnUnauthorized: false,
    },
  )
}

export async function register(email: string, password: string): Promise<RegistrationResponse> {
  return apiFetch<RegistrationResponse>(
    '/register',
    {
      method: 'POST',
      body: JSON.stringify({ email, password }),
    },
    {
      accept: 'application/json',
      authenticate: false,
      contentType: 'application/json',
      redirectOnUnauthorized: false,
    },
  )
}
