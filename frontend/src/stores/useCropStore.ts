import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { apiFetch, createResource, deleteResource, loadAnalytics, loadHydraCollection, patchResource } from '../lib/api'
import type {
  CropDto,
  CropWritePayload,
  GeneticCycleAverageDto,
  GeneticDto,
  GeneticWritePayload,
  JournalEntryDto,
  JournalEntryWritePayload,
  OperationalServiceDto,
  OperationalServiceWritePayload,
} from '../types/api'

let pollHandle: number | null = null

export const useCropStore = defineStore('crop', () => {
  const crops = ref<CropDto[]>([])
  const genetics = ref<GeneticDto[]>([])
  const services = ref<OperationalServiceDto[]>([])
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
      const [cropItems, journalItems, analyticsPayload, geneticItems, serviceItems] = await Promise.all([
        loadHydraCollection<CropDto>('/crops'),
        loadHydraCollection<JournalEntryDto>('/journal_entries'),
        loadAnalytics('/analytics/cycle-average'),
        loadHydraCollection<GeneticDto>('/genetics'),
        loadHydraCollection<OperationalServiceDto>('/operational_services'),
      ])

      crops.value = cropItems
      genetics.value = geneticItems.sort((left, right) => left.code.localeCompare(right.code))
      services.value = serviceItems.sort((left, right) => left.position - right.position || left.name.localeCompare(right.name))
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
    return createJournalEntry({
      crop: cropIri,
      type,
      occurredAt: new Date().toISOString(),
      notes: notes ?? null,
      metadata: {
        source: 'quick-action',
        capturedAt: new Date().toISOString(),
      },
    }, 'Quick action failed.')
  }

  async function createJournalEntry(payload: JournalEntryWritePayload, failureMessage = 'Journal entry creation failed.') {
    error.value = null

    try {
      await createResource('/journal_entries', payload as unknown as Record<string, unknown>)
      await loadDashboard()
    } catch (caughtError) {
      error.value = caughtError instanceof Error ? caughtError.message : failureMessage
      throw caughtError
    }
  }

  async function createCrop(payload: CropWritePayload) {
    await mutateCollection(() => createResource('/crops', payload as unknown as Record<string, unknown>), 'Crop creation failed.')
  }

  async function updateCrop(cropIri: string, payload: Partial<CropWritePayload>) {
    await mutateCollection(() => patchResource(cropIri, payload as unknown as Record<string, unknown>), 'Crop update failed.')
  }

  async function deleteCrop(cropIri: string) {
    await mutateCollection(() => deleteResource(cropIri), 'Crop deletion failed.')
  }

  async function createGenetic(payload: GeneticWritePayload) {
    await mutateCollection(() => createResource('/genetics', payload as unknown as Record<string, unknown>), 'Genetic creation failed.')
  }

  async function updateGenetic(geneticIri: string, payload: Partial<GeneticWritePayload>) {
    await mutateCollection(() => patchResource(geneticIri, payload as unknown as Record<string, unknown>), 'Genetic update failed.')
  }

  async function deleteGenetic(geneticIri: string) {
    await mutateCollection(() => deleteResource(geneticIri), 'Genetic deletion failed.')
  }

  async function createService(payload: OperationalServiceWritePayload) {
    await mutateCollection(() => createResource('/operational_services', payload as unknown as Record<string, unknown>), 'Service creation failed.')
  }

  async function updateService(serviceIri: string, payload: Partial<OperationalServiceWritePayload>) {
    await mutateCollection(() => patchResource(serviceIri, payload as unknown as Record<string, unknown>), 'Service update failed.')
  }

  async function deleteService(serviceIri: string) {
    await mutateCollection(() => deleteResource(serviceIri), 'Service deletion failed.')
  }

  async function applyTransition(cropId: string, transition: 'start_vegetative' | 'start_flowering' | 'harvest', payload: Record<string, unknown> = {}) {
    await mutateCollection(
      () => apiFetch(`/crops/${cropId}/transitions/${transition}`, {
        method: 'POST',
        body: JSON.stringify(payload),
      }, { accept: 'application/json', contentType: 'application/json' }),
      'Crop transition failed.',
    )
  }

  async function mutateCollection(action: () => Promise<unknown>, fallbackMessage: string) {
    error.value = null

    try {
      await action()
      await loadDashboard()
    } catch (caughtError) {
      error.value = caughtError instanceof Error ? caughtError.message : fallbackMessage
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
    genetics,
    services,
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
    createJournalEntry,
    createCrop,
    updateCrop,
    deleteCrop,
    createGenetic,
    updateGenetic,
    deleteGenetic,
    createService,
    updateService,
    deleteService,
    applyTransition,
    startRealtimePolling,
    stopRealtimePolling,
  }
})
