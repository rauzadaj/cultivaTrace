import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { apiFetch, loadAnalytics, loadHydraCollection } from '../lib/api'
import type { CropDto, GeneticCycleAverageDto, JournalEntryDto } from '../types/api'

let pollHandle: number | null = null

export const useCropStore = defineStore('crop', () => {
  const crops = ref<CropDto[]>([])
  const journalEntries = ref<JournalEntryDto[]>([])
  const cycleAverages = ref<GeneticCycleAverageDto[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const lastSyncedAt = ref<string | null>(null)

  const activeCrops = computed(() => crops.value.filter((crop) => crop.currentStage !== 'harvest'))
  const harvestedCrops = computed(() => crops.value.filter((crop) => crop.currentStage === 'harvest'))
  const latestEntries = computed(() => journalEntries.value.slice(0, 8))
  const averageYield = computed(() => {
    const harvestedWithYield = harvestedCrops.value.filter((crop) => typeof crop.finalYieldGrams === 'number')

    if (!harvestedWithYield.length) {
      return null
    }

    const total = harvestedWithYield.reduce((sum, crop) => sum + (crop.finalYieldGrams ?? 0), 0)

    return Math.round(total / harvestedWithYield.length)
  })

  async function loadDashboard() {
    loading.value = true
    error.value = null

    try {
      const [cropItems, journalItems, analyticsPayload] = await Promise.all([
        loadHydraCollection<CropDto>('/crops'),
        loadHydraCollection<JournalEntryDto>('/journal_entries'),
        loadAnalytics('/analytics/cycle-average'),
      ])

      crops.value = cropItems
      journalEntries.value = journalItems.sort((left, right) => right.occurredAt.localeCompare(left.occurredAt))
      cycleAverages.value = analyticsPayload.data
      lastSyncedAt.value = new Date().toISOString()
    } catch (caughtError) {
      error.value = caughtError instanceof Error ? caughtError.message : 'Unexpected dashboard error.'
    } finally {
      loading.value = false
    }
  }

  async function appendQuickEntry(cropIri: string, type: JournalEntryDto['type'], notes?: string) {
    error.value = null

    try {
      await apiFetch('/journal_entries', {
        method: 'POST',
        body: JSON.stringify({
          crop: cropIri,
          type,
          occurredAt: new Date().toISOString(),
          notes: notes ?? null,
          metadata: {
            source: 'quick-action',
            capturedAt: new Date().toISOString(),
          },
        }),
      })

      await loadDashboard()
    } catch (caughtError) {
      error.value = caughtError instanceof Error ? caughtError.message : 'Quick action failed.'
      throw caughtError
    }
  }

  function startRealtimePolling(intervalMs = 15000) {
    stopRealtimePolling()
    pollHandle = window.setInterval(() => {
      void loadDashboard()
    }, intervalMs)
  }

  function stopRealtimePolling() {
    if (pollHandle !== null) {
      window.clearInterval(pollHandle)
      pollHandle = null
    }
  }

  return {
    crops,
    journalEntries,
    cycleAverages,
    loading,
    error,
    lastSyncedAt,
    activeCrops,
    harvestedCrops,
    latestEntries,
    averageYield,
    loadDashboard,
    appendQuickEntry,
    startRealtimePolling,
    stopRealtimePolling,
  }
})
