import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import type { Room } from '@/types/api'
import { usePlantsStore } from './plants'

const { listRoomsMock, listPlantsMock, listEventsMock } = vi.hoisted(() => ({
  listRoomsMock: vi.fn(),
  listPlantsMock: vi.fn(),
  listEventsMock: vi.fn(),
}))

vi.mock('@/services/api', () => ({
  roomsApi: { list: listRoomsMock },
  plantsApi: { list: listPlantsMock },
  plantEventsApi: { list: listEventsMock },
  strainsApi: {},
}))

function room(id: string, capacityMax = 100, activePlantCount = 40): Room {
  return { id, name: `Salle ${id}`, type: 'veg', capacityMax, activePlantCount, farm: '/api/farms/farm-1' }
}

describe('plants store room loading', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    vi.resetAllMocks()
  })

  it.each(['prefixed', 'unprefixed'] as const)('loads every page using %s totals', async (format) => {
    const firstRoom = room('1')
    const secondRoom = room('2', 200, 80)
    const page = (members: Room[]) => format === 'prefixed'
      ? { data: { 'hydra:member': members, 'hydra:totalItems': 2 } }
      : { data: { member: members, totalItems: 2 } }
    listRoomsMock.mockResolvedValueOnce(page([firstRoom])).mockResolvedValueOnce(page([secondRoom]))
    const store = usePlantsStore()

    await store.fetchRooms()

    expect(listRoomsMock).toHaveBeenNthCalledWith(1, { itemsPerPage: 100, page: 1 })
    expect(listRoomsMock).toHaveBeenNthCalledWith(2, { itemsPerPage: 100, page: 2 })
    expect(listRoomsMock).toHaveBeenCalledTimes(2)
    expect(store.rooms).toEqual([firstRoom, secondRoom])
    expect(store.rooms.reduce((total, entry) => total + entry.capacityMax, 0)).toBe(300)
    expect(store.rooms.reduce((total, entry) => total + (entry.activePlantCount ?? 0), 0)).toBe(120)
    expect(store.roomsLoaded).toBe(true)
    expect(store.roomsLoading).toBe(false)
    expect(store.roomsError).toBeNull()
  })

  it.each(['prefixed', 'unprefixed'] as const)('loads the next page using %s links without totals', async (format) => {
    const firstRoom = room('1')
    const secondRoom = room('2')
    listRoomsMock.mockResolvedValueOnce({
      data: format === 'prefixed'
        ? { 'hydra:member': [firstRoom], 'hydra:view': { 'hydra:next': '/api/rooms?page=2' } }
        : { member: [firstRoom], view: { next: '/api/rooms?page=2' } },
    }).mockResolvedValueOnce({ data: { member: [secondRoom] } })
    const store = usePlantsStore()

    await store.fetchRooms()

    expect(listRoomsMock).toHaveBeenCalledTimes(2)
    expect(store.rooms).toEqual([firstRoom, secondRoom])
    expect(store.roomsLoaded).toBe(true)
  })

  it('shares concurrent loads and keeps the previous collection until every page succeeds', async () => {
    const previousRoom = room('previous')
    const firstRoom = room('1')
    const secondRoom = room('2')
    let finishSecondPage!: (response: { data: { member: Room[] } }) => void
    listRoomsMock.mockResolvedValueOnce({ data: { member: [firstRoom], totalItems: 2 } })
      .mockReturnValueOnce(new Promise((resolve) => { finishSecondPage = resolve }))
    const store = usePlantsStore()
    store.rooms = [previousRoom]

    const request = store.fetchRooms()
    await Promise.resolve()
    const concurrentRequest = store.fetchRooms()

    expect(listRoomsMock).toHaveBeenCalledTimes(2)
    expect(store.rooms).toEqual([previousRoom])
    expect(store.roomsLoading).toBe(true)
    expect(store.roomsLoaded).toBe(false)

    finishSecondPage({ data: { member: [secondRoom] } })
    await Promise.all([request, concurrentRequest])

    expect(listRoomsMock).toHaveBeenCalledTimes(2)
    expect(store.rooms).toEqual([firstRoom, secondRoom])
    expect(store.roomsLoading).toBe(false)
    expect(store.roomsLoaded).toBe(true)
  })

  it('propagates a shared request failure to every caller and allows a fresh retry', async () => {
    const failure = new Error('Rooms unavailable')
    let failRequest!: (reason: Error) => void
    listRoomsMock.mockReturnValueOnce(new Promise((_resolve, reject) => { failRequest = reject }))
      .mockResolvedValueOnce({ data: { member: [room('1')], totalItems: 1 } })
    const store = usePlantsStore()

    const requests = Promise.allSettled([store.fetchRooms(), store.fetchRooms()])
    expect(listRoomsMock).toHaveBeenCalledTimes(1)
    expect(store.roomsLoading).toBe(true)
    failRequest(failure)

    expect(await requests).toEqual([
      { status: 'rejected', reason: failure },
      { status: 'rejected', reason: failure },
    ])
    expect(store.roomsLoading).toBe(false)
    expect(store.roomsError).toBe(failure.message)
    expect(store.roomsLoaded).toBe(false)

    await store.fetchRooms()

    expect(listRoomsMock).toHaveBeenCalledTimes(2)
    expect(store.rooms).toEqual([room('1')])
    expect(store.roomsLoading).toBe(false)
    expect(store.roomsError).toBeNull()
    expect(store.roomsLoaded).toBe(true)
  })

  it('reports a later-page failure without publishing partial rooms and clears the error on retry', async () => {
    const previousRoom = room('previous')
    const updatedRoom = room('updated')
    const failure = new Error('Room page unavailable')
    listRoomsMock.mockResolvedValueOnce({ data: { member: [room('1')], totalItems: 2 } })
      .mockRejectedValueOnce(failure)
      .mockResolvedValueOnce({ data: { member: [updatedRoom], totalItems: 1 } })
    const store = usePlantsStore()
    store.rooms = [previousRoom]
    store.roomsLoaded = true

    await expect(store.fetchRooms()).rejects.toBe(failure)

    expect(store.rooms).toEqual([previousRoom])
    expect(store.roomsError).toBe(failure.message)
    expect(store.roomsLoading).toBe(false)
    expect(store.roomsLoaded).toBe(false)
    expect(store.error).toBeNull()

    const retry = store.fetchRooms()
    expect(store.roomsError).toBeNull()
    expect(store.roomsLoading).toBe(true)
    await retry

    expect(store.rooms).toEqual([updatedRoom])
    expect(store.roomsError).toBeNull()
    expect(store.roomsLoaded).toBe(true)
  })

  it('marks an empty collection as successfully loaded', async () => {
    listRoomsMock.mockResolvedValueOnce({ data: { member: [], totalItems: 0 } })
    const store = usePlantsStore()

    await store.fetchRooms()

    expect(listRoomsMock).toHaveBeenCalledTimes(1)
    expect(store.rooms).toEqual([])
    expect(store.roomsLoaded).toBe(true)
    expect(store.roomsError).toBeNull()
    expect(store.roomsLoading).toBe(false)
  })

  it('scopes cached rooms to an organization and reloads on tenant change', async () => {
    listRoomsMock.mockResolvedValueOnce({ data: { member: [room('tenant-a')] } })
      .mockResolvedValueOnce({ data: { member: [room('tenant-b')] } })
    const store = usePlantsStore()

    await store.fetchRooms('tenant-a')
    expect(store.rooms[0].id).toBe('tenant-a')

    await store.fetchRooms('tenant-b')
    expect(store.rooms[0].id).toBe('tenant-b')
    expect(store.roomsOrganizationId).toBe('tenant-b')
    expect(listRoomsMock).toHaveBeenCalledTimes(2)
  })

  it('keeps room loading and errors independent of failed plant requests', async () => {
    const plantFailure = new Error('Plants unavailable')
    listPlantsMock.mockRejectedValueOnce(plantFailure)
    listRoomsMock.mockResolvedValueOnce({ data: { member: [room('1')] } })
    const store = usePlantsStore()

    await expect(store.fetchPlants()).rejects.toBe(plantFailure)
    const request = store.fetchRooms()

    expect(store.roomsLoading).toBe(true)
    expect(store.loading).toBe(false)
    expect(store.error).toBe(plantFailure.message)
    expect(store.roomsError).toBeNull()
    await request

    expect(store.rooms).toHaveLength(1)
    expect(store.roomsLoaded).toBe(true)
    expect(store.roomsError).toBeNull()
    expect(store.error).toBe(plantFailure.message)
  })

  it('retains successfully loaded rooms when plant events fail', async () => {
    const eventFailure = new Error('Events unavailable')
    listRoomsMock.mockResolvedValueOnce({ data: { member: [room('1')] } })
    listEventsMock.mockRejectedValueOnce(eventFailure)
    const store = usePlantsStore()
    await store.fetchRooms()

    await expect(store.fetchEvents('plant-1')).rejects.toBe(eventFailure)

    expect(store.rooms).toHaveLength(1)
    expect(store.roomsLoaded).toBe(true)
    expect(store.roomsLoading).toBe(false)
    expect(store.roomsError).toBeNull()
    expect(store.error).toBe(eventFailure.message)
  })
})
