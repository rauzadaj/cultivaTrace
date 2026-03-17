/**
 * apps/frontend/src/composables/useMercure.ts
 *
 * Composable pour recevoir les événements temps réel via Mercure (SSE).
 * Utilisé pour les mises à jour IoT en temps réel.
 *
 * Usage dans un composant :
 *
 *   const { subscribe, unsubscribe } = useMercure()
 *
 *   onMounted(() => {
 *     subscribe(`/sensors/${sensorId}`, (data) => {
 *       console.log('Nouvelle lecture capteur :', data)
 *     })
 *   })
 *
 *   onUnmounted(() => unsubscribe())
 */

import { ref, onUnmounted } from 'vue'
import { useAuthStore } from '@/stores/auth'

export function useMercure() {
  const eventSources = ref<Map<string, EventSource>>(new Map())
  const authStore = useAuthStore()

  const mercureUrl = import.meta.env.VITE_MERCURE_URL ?? '/.well-known/mercure'

  function subscribe(topic: string, callback: (data: unknown) => void): void {
    if (eventSources.value.has(topic)) {
      return // déjà abonné
    }

    const url = new URL(mercureUrl)
    url.searchParams.append('topic', topic)

    const token = authStore.token
    // Le token Mercure est différent du JWT API — à configurer dans le backend
    // Pour simplifier en dev, utiliser le même JWT si Mercure est configuré pour l'accepter
    const es = new EventSource(`${url.toString()}`, {
      withCredentials: true,
    } as EventSourceInit)

    es.addEventListener('message', (event: MessageEvent) => {
      try {
        const data = JSON.parse(event.data)
        callback(data)
      } catch {
        console.error('Mercure: impossible de parser le message', event.data)
      }
    })

    es.addEventListener('error', () => {
      console.warn(`Mercure: connexion perdue pour le topic ${topic}, reconnexion automatique...`)
    })

    eventSources.value.set(topic, es)
  }

  function unsubscribe(topic?: string): void {
    if (topic) {
      eventSources.value.get(topic)?.close()
      eventSources.value.delete(topic)
    } else {
      // Fermer toutes les connexions
      eventSources.value.forEach(es => es.close())
      eventSources.value.clear()
    }
  }

  // Nettoyage automatique quand le composant est détruit
  onUnmounted(() => unsubscribe())

  return { subscribe, unsubscribe }
}
