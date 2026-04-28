import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { alertsApi } from '@/services/api'
import type { HydraCollection, PersistentAlert } from '@/types/api'

function collectionMembers<T>(collection: HydraCollection<T>): T[] {
  return collection['hydra:member'] ?? collection.member ?? []
}

export const useAlertsStore = defineStore('alerts', () => {
  const alerts = ref<PersistentAlert[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  const unreadAlerts = computed(() => alerts.value.filter((alert) => !alert.acknowledgedAt))
  const unreadCount = computed(() => unreadAlerts.value.length)

  async function fetchAlerts(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const { data } = await alertsApi.list()
      alerts.value = collectionMembers(data)
    } catch (caughtError) {
      error.value = caughtError instanceof Error ? caughtError.message : 'Impossible de charger les alertes.'
      throw caughtError
    } finally {
      loading.value = false
    }
  }

  async function acknowledgeAlert(id: string): Promise<void> {
    const { data } = await alertsApi.acknowledge(id)
    alerts.value = alerts.value.map((alert) => alert.id === id ? data : alert)
  }

  return {
    alerts,
    loading,
    error,
    unreadAlerts,
    unreadCount,
    fetchAlerts,
    acknowledgeAlert,
  }
})
