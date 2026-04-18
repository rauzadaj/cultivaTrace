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
 *     subscribe(`https://cultivatrace.com/tenants/${orgId}/rooms/${roomId}`, (data) => {
 *       console.log('Nouvelle lecture :', data)
 *     })
 *   })
 *
 *   onUnmounted(() => unsubscribe())
 */

import { ref, onUnmounted } from 'vue'

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

  const mercureUrl = import.meta.env.VITE_MERCURE_PUBLIC_URL
    ?? '/.well-known/mercure'

  function buildMercureUrl(topic: string): string {
    const base = typeof window !== 'undefined' ? window.location.origin : 'http://localhost:5173'
    const url = new URL(mercureUrl, base)
    url.searchParams.append('topic', topic)

    return url.toString()
  }

  function subscribe(topic: string, callback: (data: SensorUpdate) => void): void {
    if (eventSources.value.has(topic)) return

    const es = new EventSource(buildMercureUrl(topic))

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
  }

  function unsubscribe(topic?: string): void {
    if (topic) {
      eventSources.value.get(topic)?.close()
      eventSources.value.delete(topic)
    } else {
      eventSources.value.forEach(es => es.close())
      eventSources.value.clear()
    }
  }

  onUnmounted(() => unsubscribe())

  return { subscribe, unsubscribe }
}
