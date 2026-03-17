/**
 * apps/frontend/src/composables/useOfflineQueue.ts
 *
 * Queue des actions offline — stockage IndexedDB via localStorage simplifié.
 * Les actions POST/PATCH effectuées sans connexion sont mises en file
 * et rejouées dans l'ordre quand la connexion revient.
 *
 * Usage :
 *   const { enqueue, syncQueue, pendingCount, isOnline } = useOfflineQueue()
 *
 *   // Au lieu de appeler l'API directement :
 *   if (!isOnline.value) {
 *     await enqueue({ url: '/plant-events', method: 'POST', body: eventData })
 *   } else {
 *     await plantEventsApi.append(eventData)
 *   }
 */

import { ref, onMounted, onUnmounted } from 'vue'
import http from '@/services/api'

interface QueuedAction {
  id: string
  url: string
  method: 'POST' | 'PATCH'
  body: unknown
  headers?: Record<string, string>
  queuedAt: string
  retries: number
}

const QUEUE_KEY = 'cannas_offline_queue'

export function useOfflineQueue() {
  const isOnline = ref(navigator.onLine)
  const queue = ref<QueuedAction[]>(loadQueue())
  const syncing = ref(false)

  const pendingCount = ref(queue.value.length)

  function loadQueue(): QueuedAction[] {
    try {
      return JSON.parse(localStorage.getItem(QUEUE_KEY) ?? '[]')
    } catch {
      return []
    }
  }

  function saveQueue(): void {
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue.value))
    pendingCount.value = queue.value.length
  }

  async function enqueue(action: Omit<QueuedAction, 'id' | 'queuedAt' | 'retries'>): Promise<void> {
    const queued: QueuedAction = {
      ...action,
      id: crypto.randomUUID(),
      queuedAt: new Date().toISOString(),
      retries: 0,
    }
    queue.value.push(queued)
    saveQueue()
    console.info(`[OfflineQueue] Action mise en file : ${action.method} ${action.url}`)
  }

  async function syncQueue(): Promise<void> {
    if (syncing.value || queue.value.length === 0 || !isOnline.value) {
      return
    }

    syncing.value = true
    const failed: QueuedAction[] = []

    for (const action of queue.value) {
      try {
        await http.request({
          url: action.url,
          method: action.method,
          data: action.body,
          headers: action.headers,
        })
        console.info(`[OfflineQueue] Synchronisé : ${action.method} ${action.url}`)
      } catch (error) {
        action.retries++
        if (action.retries < 3) {
          failed.push(action)
        } else {
          console.error(`[OfflineQueue] Abandon après 3 tentatives : ${action.url}`, error)
        }
      }
    }

    queue.value = failed
    saveQueue()
    syncing.value = false

    if (failed.length === 0) {
      console.info('[OfflineQueue] Toutes les actions synchronisées')
    }
  }

  function handleOnline(): void {
    isOnline.value = true
    syncQueue()
  }

  function handleOffline(): void {
    isOnline.value = false
  }

  onMounted(() => {
    window.addEventListener('online', handleOnline)
    window.addEventListener('offline', handleOffline)
    // Sync au démarrage si en ligne
    if (isOnline.value && queue.value.length > 0) {
      syncQueue()
    }
  })

  onUnmounted(() => {
    window.removeEventListener('online', handleOnline)
    window.removeEventListener('offline', handleOffline)
  })

  return { isOnline, pendingCount, syncing, enqueue, syncQueue }
}
