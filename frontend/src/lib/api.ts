import type { AnalyticsResponse, HydraCollection } from '../types/api'
import { useUserStore } from '../stores/useUserStore'

async function parseError(response: Response): Promise<string> {
  try {
    const payload = await response.json()

    return payload.detail ?? payload['hydra:description'] ?? payload.message ?? `HTTP ${response.status}`
  } catch {
    return `HTTP ${response.status}`
  }
}

export async function apiFetch<T>(path: string, init: RequestInit = {}): Promise<T> {
  const userStore = useUserStore()
  const baseUrl = userStore.normalizedApiBase
  const url = `${baseUrl}${path.startsWith('/') ? path : `/${path}`}`
  const headers = new Headers(init.headers)

  headers.set('Accept', 'application/ld+json, application/json')

  if (init.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/ld+json')
  }

  if (userStore.token) {
    headers.set('Authorization', `Bearer ${userStore.token}`)
  }

  const response = await fetch(url, { ...init, headers })

  if (!response.ok) {
    throw new Error(await parseError(response))
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
  return apiFetch<AnalyticsResponse>(path, { headers: { Accept: 'application/json' } })
}
