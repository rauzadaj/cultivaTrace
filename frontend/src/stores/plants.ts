import { computed, ref } from 'vue'
import { defineStore } from 'pinia'
import { plantEventsApi, plantsApi, roomsApi, strainsApi } from '@/services/api'
import type {
  HydraCollection,
  Plant,
  PlantCardSummary,
  PlantEvent,
  PlantStage,
  Room,
  Strain,
} from '@/types/api'

function relationId(value: string | { id: string } | undefined | null): string | null {
  if (!value) {
    return null
  }

  if (typeof value === 'string') {
    const segments = value.split('/')

    return segments[segments.length - 1] || value
  }

  return value.id
}

function relationIri(value: { '@id'?: string; id: string } | undefined | null, resource: string): string | null {
  if (!value) {
    return null
  }

  return value['@id'] ?? `/api/${resource}/${value.id}`
}

function formatPlantName(plant: Plant, strains: Map<string, Strain>): string {
  const strainId = relationId(plant.strain)
  const strain = strainId ? strains.get(strainId) : null

  if (plant.rfidTag?.trim()) {
    return plant.rfidTag.trim()
  }

  if (strain?.name) {
    return `${strain.name} · ${plant.id.slice(0, 8)}`
  }

  return `Plant ${plant.id.slice(0, 8)}`
}

function collectionMembers<T>(collection: HydraCollection<T>): T[] {
  return collection['hydra:member'] ?? collection.member ?? []
}

function collectionTotalItems<T>(collection: HydraCollection<T>): number {
  return collection['hydra:totalItems'] ?? collection.totalItems ?? collectionMembers(collection).length
}

export const usePlantsStore = defineStore('plants', () => {
  const plants = ref<Plant[]>([])
  const rooms = ref<Room[]>([])
  const strains = ref<Strain[]>([])
  const currentPlant = ref<Plant | null>(null)
  const currentEvents = ref<PlantEvent[]>([])
  const recentEvents = ref<PlantEvent[]>([])
  const totalItems = ref(0)
  const loading = ref(false)
  const error = ref<string | null>(null)

  const filters = ref({
    'room.id': undefined as string | undefined,
    'strain.id': undefined as string | undefined,
    stage: undefined as PlantStage | undefined,
    status: 'active' as string | undefined,
    page: 1,
    itemsPerPage: 30,
  })

  const roomsById = computed(() => new Map<string, Room>(rooms.value.map((room: Room) => [room.id, room])))
  const strainsById = computed(() => new Map<string, Strain>(strains.value.map((strain: Strain) => [strain.id, strain])))

  const plantCards = computed<PlantCardSummary[]>(() => plants.value.map((plant: Plant) => {
    const roomId = relationId(plant.room)
    const room = roomId ? roomsById.value.get(roomId) : null
    const strainId = relationId(plant.strain)
    const strain = strainId ? strainsById.value.get(strainId) : null

    return {
      id: plant.id,
      iri: plant['@id'],
      name: formatPlantName(plant, strainsById.value),
      strain: strain?.name ?? 'Genetique non renseignee',
      room: room?.name ?? 'Salle non renseignee',
      stage: plant.stage,
      status: plant.status,
      ageInDays: plant.ageInDays ?? 0,
      batchCode: plant.id.slice(0, 8).toUpperCase(),
    }
  }))

  const activePlants = computed(() => plantCards.value.filter((plant: PlantCardSummary) => plant.status === 'active'))
  const harvestedPlants = computed(() => plantCards.value.filter((plant: PlantCardSummary) => plant.status === 'harvested'))

  async function fetchRooms(): Promise<void> {
    const { data } = await roomsApi.list({ itemsPerPage: 100 })
    rooms.value = collectionMembers(data)
  }

  async function fetchStrains(): Promise<void> {
    const { data } = await strainsApi.list({ itemsPerPage: 100 })
    strains.value = collectionMembers(data)
  }

  async function fetchSupportData(): Promise<void> {
    await Promise.all([fetchRooms(), fetchStrains()])
  }

  async function ensureSupportData(): Promise<void> {
    if (rooms.value.length && strains.value.length) {
      return
    }

    await fetchSupportData()
  }

  async function fetchPlants(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const params = Object.fromEntries(Object.entries(filters.value).filter(([, value]) => value !== undefined))
      const { data } = await plantsApi.list(params)
      plants.value = collectionMembers(data)
      totalItems.value = collectionTotalItems(data)
    } catch (caughtError) {
      error.value = caughtError instanceof Error ? caughtError.message : 'Impossible de charger les plants.'
      throw caughtError
    } finally {
      loading.value = false
    }
  }

  async function bootstrap(): Promise<void> {
    loading.value = true
    error.value = null

    try {
      await Promise.all([fetchSupportData(), fetchPlants(), fetchRecentEvents()])
    } finally {
      loading.value = false
    }
  }

  async function fetchPlant(id: string): Promise<void> {
    loading.value = true
    error.value = null

    try {
      const [{ data }] = await Promise.all([
        plantsApi.get(id),
        rooms.value.length && strains.value.length ? Promise.resolve(null) : ensureSupportData(),
      ])
      currentPlant.value = data
    } catch (caughtError) {
      error.value = caughtError instanceof Error ? caughtError.message : 'Impossible de charger ce plant.'
      throw caughtError
    } finally {
      loading.value = false
    }
  }

  async function fetchEvents(plantId: string): Promise<void> {
    try {
      const { data } = await plantEventsApi.list(plantId, { itemsPerPage: 50 })
      currentEvents.value = collectionMembers(data)
    } catch (caughtError) {
      error.value = caughtError instanceof Error ? caughtError.message : 'Impossible de charger les evenements.'
      throw caughtError
    }
  }

  async function fetchRecentEvents(): Promise<void> {
    try {
      const { data } = await plantEventsApi.listAll({ itemsPerPage: 20 })
      recentEvents.value = collectionMembers(data)
    } catch (caughtError) {
      error.value = caughtError instanceof Error ? caughtError.message : 'Impossible de charger les evenements recents.'
      throw caughtError
    }
  }

  async function createPlant(payload: {
    room: string
    strain?: string | null
    germinatedAt: string
    rfidTag?: string | null
  }): Promise<Plant> {
    const body = {
      room: payload.room,
      strain: payload.strain || null,
      germinatedAt: payload.germinatedAt,
      rfidTag: payload.rfidTag?.trim() || null,
    }
    const { data } = await plantsApi.create(body as Partial<Plant>)
    plants.value.unshift(data)
    totalItems.value += 1
    currentPlant.value = data

    return data
  }

  async function changeStage(plantId: string, to: PlantStage): Promise<void> {
    const { data } = await plantsApi.update(plantId, { stage: to })
      plants.value = plants.value.map((plant: Plant) => plant.id === plantId ? data : plant)

    if (currentPlant.value?.id === plantId) {
      currentPlant.value = data
    }

    await Promise.all([fetchEvents(plantId), fetchRecentEvents()])
  }

  function roomLabel(plant: Plant | PlantCardSummary | null): string {
    if (!plant) {
      return 'Salle non renseignee'
    }

    if ('room' in plant && typeof plant.room === 'string') {
      const room = roomsById.value.get(relationId(plant.room) ?? '')

      return room?.name ?? 'Salle non renseignee'
    }

    if ('room' in plant && typeof plant.room === 'object' && plant.room && 'name' in plant.room) {
      return plant.room.name
    }

    return 'room' in plant && typeof plant.room === 'string' ? plant.room : 'Salle non renseignee'
  }

  function strainLabel(plant: Plant | PlantCardSummary | null): string {
    if (!plant) {
      return 'Genetique non renseignee'
    }

    if ('strain' in plant && typeof plant.strain === 'string') {
      const strain = strainsById.value.get(relationId(plant.strain) ?? '')

      return strain?.name ?? 'Genetique non renseignee'
    }

    if ('strain' in plant && typeof plant.strain === 'object' && plant.strain && 'name' in plant.strain) {
      return plant.strain.name
    }

    return 'strain' in plant && typeof plant.strain === 'string' ? plant.strain : 'Genetique non renseignee'
  }

  function plantName(plant: Plant | null): string {
    if (!plant) {
      return 'Plant'
    }

    return formatPlantName(plant, strainsById.value)
  }

  function roomIriList() {
    return rooms.value.map((room: Room) => ({
      label: room.name,
      value: relationIri(room, 'rooms') ?? `/api/rooms/${room.id}`,
    }))
  }

  function strainIriList() {
    return strains.value.map((strain: Strain) => ({
      label: `${strain.name} · ${strain.genetics}`,
      value: relationIri(strain, 'strains') ?? `/api/strains/${strain.id}`,
    }))
  }

  function setFilter<K extends keyof typeof filters.value>(key: K, value: typeof filters.value[K]): void {
    filters.value[key] = value
    filters.value.page = 1
  }

  return {
    plants,
    rooms,
    strains,
    currentPlant,
    currentEvents,
    recentEvents,
    totalItems,
    loading,
    error,
    filters,
    plantCards,
    activePlants,
    harvestedPlants,
    roomsById,
    strainsById,
    bootstrap,
    fetchSupportData,
    ensureSupportData,
    fetchRooms,
    fetchStrains,
    fetchPlants,
    fetchPlant,
    fetchEvents,
    fetchRecentEvents,
    createPlant,
    changeStage,
    roomLabel,
    strainLabel,
    plantName,
    roomIriList,
    strainIriList,
    setFilter,
  }
})
