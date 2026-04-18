/**
 * frontend/src/composables/useMercure.ts
 *
 * Composable pour recevoir les événements IoT en temps réel via Mercure SSE.
 *
 * Usage dans un composant :
 *
 *   const { subscribe, unsubscribe } = useMercure()
 *
 *   onMounted(() => {
 *     subscribe(`cannas/${orgId}/rooms/${roomId}`, (data) => {
 *       console.log('Nouvelle lecture :', data)
 *     })
 *   })
 *
 *   onUnmounted(() => unsubscribe())
 */

import { ref, onUnmounted } from 'vue'
import http from '@/services/api'

export interface SensorUpdate {
  sensorId:   string
  roomId:     string
  type:       string
  value:      number
  unit:       string
  recordedAt: string
  vpd?: {
    status:      string
    message:     string
    vpd:         number
    optimal_min: number
    optimal_max: number
    stage:       string
  } | null
}

export function useMercure() {
  const eventSources = ref<Map<string, EventSource>>(new Map())
  const callbacks = new Map<string, (data: SensorUpdate) => void>()
  const refreshTimers = new Map<string, number>()

  const mercureUrl = import.meta.env.VITE_MERCURE_PUBLIC_URL
    ?? '/.well-known/mercure'
  const tokenRefreshLeewayMs = 60_000

  let mercureToken: { value: string; expiresAt: number } | null = null
  let mercureTokenPromise: Promise<{ value: string; expiresAt: number }> | null = null

  function normalizeTopic(topic: string): string {
    if (topic.startsWith('https://')) {
      return topic
    }

    const legacyMatch = /^cannas\/([^/]+)\/rooms\/([^/]+)$/.exec(topic)
    if (legacyMatch) {
      const [, tenantId, roomId] = legacyMatch
      return `https://cultivatrace.com/tenants/${tenantId}/rooms/${roomId}`
    }

    return topic
  }

  function buildMercureUrl(topic: string, token: string): string {
    const base = typeof window !== 'undefined' ? window.location.origin : 'http://localhost:5173'
    const url = new URL(mercureUrl, base)
    url.searchParams.append('topic', normalizeTopic(topic))
    url.searchParams.append('authorization', token)

    return url.toString()
  }

  function decodeTokenExpiry(token: string): number {
    const [, rawPayload = ''] = token.split('.')
    const normalizedPayload = rawPayload
      .replace(/-/g, '+')
      .replace(/_/g, '/')
      .padEnd(Math.ceil(rawPayload.length / 4) * 4, '=')

    try {
      const payload = JSON.parse(window.atob(normalizedPayload)) as { exp?: number }
      return typeof payload.exp === 'number' ? payload.exp * 1000 : Date.now() + (15 * 60 * 1000)
    } catch {
      return Date.now() + (15 * 60 * 1000)
    }
  }

  async function fetchMercureToken(forceRefresh = false): Promise<{ value: string; expiresAt: number }> {
    const now = Date.now()

    if (
      !forceRefresh
      && mercureToken
      && (mercureToken.expiresAt - now) > tokenRefreshLeewayMs
    ) {
      return mercureToken
    }

    if (!mercureTokenPromise) {
      mercureTokenPromise = http
        .get<{ token: string; expiresIn: number; expiresAt: string }>('/mercure/token', {
          headers: { Accept: 'application/json' },
        })
        .then(({ data }) => {
          const expiresAt = decodeTokenExpiry(data.token)
          mercureToken = {
            value: data.token,
            expiresAt,
          }

          return mercureToken
        })
        .finally(() => {
          mercureTokenPromise = null
        })
    }

    return mercureTokenPromise
  }

  function clearRefreshTimer(topic: string): void {
    const timer = refreshTimers.get(topic)
    if (timer !== undefined) {
      window.clearTimeout(timer)
      refreshTimers.delete(topic)
    }
  }

  function scheduleRefresh(topic: string): void {
    clearRefreshTimer(topic)

    if (!mercureToken) {
      return
    }

    const callback = callbacks.get(topic)
    if (!callback) {
      return
    }

    const refreshInMs = Math.max(mercureToken.expiresAt - Date.now() - tokenRefreshLeewayMs, 5_000)

    const timer = window.setTimeout(() => {
      void openEventSource(topic, callback, true)
    }, refreshInMs)

    refreshTimers.set(topic, timer)
  }

  function scheduleReconnect(topic: string, callback: (data: SensorUpdate) => void, delayMs = 5_000): void {
    clearRefreshTimer(topic)

    const timer = window.setTimeout(() => {
      void openEventSource(topic, callback)
    }, delayMs)

    refreshTimers.set(topic, timer)
  }

  async function openEventSource(
    topic: string,
    callback: (data: SensorUpdate) => void,
    forceRefreshToken = false,
  ): Promise<void> {
    try {
      const token = await fetchMercureToken(forceRefreshToken)
      const current = eventSources.value.get(topic)
      current?.close()

      const es = new EventSource(buildMercureUrl(topic, token.value))

      es.addEventListener('message', (event: MessageEvent) => {
        try {
          const data = JSON.parse(event.data) as SensorUpdate
          callback(data)
        } catch {
          console.warn('[Mercure] Impossible de parser:', event.data)
        }
      })

      es.addEventListener('error', () => {
        console.warn(`[Mercure] Reconnexion sur topic: ${topic}`)
        // EventSource reconnecte automatiquement
      })

      eventSources.value.set(topic, es)
      scheduleRefresh(topic)
    } catch (error) {
      console.warn(`[Mercure] Echec de connexion sur topic: ${topic}`, error)
      eventSources.value.get(topic)?.close()
      eventSources.value.delete(topic)
      scheduleReconnect(topic, callback)
    }
  }

  function subscribe(topic: string, callback: (data: SensorUpdate) => void): void {
    if (callbacks.has(topic)) return

    callbacks.set(topic, callback)
    void openEventSource(topic, callback)
  }

  function unsubscribe(topic?: string): void {
    if (topic) {
      clearRefreshTimer(topic)
      eventSources.value.get(topic)?.close()
      eventSources.value.delete(topic)
      callbacks.delete(topic)
    } else {
      refreshTimers.forEach((timer) => window.clearTimeout(timer))
      refreshTimers.clear()
      eventSources.value.forEach(es => es.close())
      eventSources.value.clear()
      callbacks.clear()
    }
  }

  onUnmounted(() => unsubscribe())

  return { subscribe, unsubscribe }
}
