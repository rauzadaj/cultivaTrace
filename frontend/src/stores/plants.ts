/**
 * apps/frontend/src/stores/plants.ts
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { plantsApi, plantEventsApi } from '@/services/api'
import type { Plant, PlantEvent, HydraCollection, PlantStage } from '@/types/api'

export const usePlantsStore = defineStore('plants', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const plants = ref<Plant[]>([])
  const currentPlant = ref<Plant | null>(null)
  const currentEvents = ref<PlantEvent[]>([])
  const totalItems = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Filtres actifs
  const filters = ref({
    'room.id': undefined as string | undefined,
    'strain.id': undefined as string | undefined,
    stage: undefined as PlantStage | undefined,
    status: 'active' as string | undefined,
    page: 1,
    itemsPerPage: 30,
  })

  // ── Getters ──────────────────────────────────────────────────────────────
  const plantsByStage = computed(() => {
    const groups: Record<PlantStage, Plant[]> = {
      germination: [], vegetation: [], flowering: [], harvest: [], archived: [],
    }
    plants.value.forEach(p => groups[p.stage]?.push(p))
    return groups
  })

  // ── Actions ──────────────────────────────────────────────────────────────
  async function fetchPlants(): Promise<void> {
    loading.value = true
    error.value = null
    try {
      const params = Object.fromEntries(
        Object.entries(filters.value).filter(([, v]) => v !== undefined)
      )
      const { data } = await plantsApi.list(params)
      plants.value = data['hydra:member']
      totalItems.value = data['hydra:totalItems']
    } catch (e: unknown) {
      error.value = 'Impossible de charger les plants'
      console.error(e)
    } finally {
      loading.value = false
    }
  }

  async function fetchPlant(id: string): Promise<void> {
    loading.value = true
    try {
      const { data } = await plantsApi.get(id)
      currentPlant.value = data
    } finally {
      loading.value = false
    }
  }

  async function fetchEvents(plantId: string): Promise<void> {
    const { data } = await plantEventsApi.list(plantId, { itemsPerPage: 50 })
    currentEvents.value = data['hydra:member']
  }

  async function createPlant(payload: Partial<Plant>): Promise<Plant> {
    const { data } = await plantsApi.create(payload)
    plants.value.unshift(data)
    return data
  }

  async function changeStage(plantId: string, to: PlantStage): Promise<void> {
    const plant = plants.value.find(p => p.id === plantId)
    const from = plant?.stage

    // 1. Appeler l'API PATCH pour changer le stade
    await plantsApi.update(plantId, { stage: to })

    // 2. Ajouter un PlantEvent dans l'audit trail
    await plantEventsApi.append({
      plant: `/api/plants/${plantId}`,
      eventType: 'stage_change',
      payload: { from, to },
    })

    // 3. Mettre à jour le store local
    if (plant) plant.stage = to
    if (currentPlant.value?.id === plantId) currentPlant.value.stage = to
  }

  async function addNote(plantId: string, notes: string, photoUrls?: string[]): Promise<void> {
    await plantEventsApi.append({
      plant: `/api/plants/${plantId}`,
      eventType: 'note',
      notes,
      photoUrls,
    })
    // Rafraîchir les events si on est sur la page détail
    if (currentPlant.value?.id === plantId) {
      await fetchEvents(plantId)
    }
  }

  function setFilter<K extends keyof typeof filters.value>(key: K, value: typeof filters.value[K]): void {
    filters.value[key] = value
    filters.value.page = 1
  }

  function resetFilters(): void {
    filters.value = {
      'room.id': undefined,
      'strain.id': undefined,
      stage: undefined,
      status: 'active',
      page: 1,
      itemsPerPage: 30,
    }
  }

  return {
    plants, currentPlant, currentEvents, totalItems, loading, error, filters,
    plantsByStage,
    fetchPlants, fetchPlant, fetchEvents, createPlant, changeStage, addNote,
    setFilter, resetFilters,
  }
})
