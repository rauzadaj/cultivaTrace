import { createPinia, setActivePinia } from 'pinia'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { useAuthStore } from '@/stores/auth'
import { usePlantsStore } from '@/stores/plants'
import type { DashboardOverviewResponse, HydraCollection, Room } from '@/types/api'
import RoomDashboard from './RoomDashboard.vue'

const { roomsList, plantsList, sensorsList, overview, subscribe, route } = vi.hoisted(() => ({
  roomsList: vi.fn(),
  plantsList: vi.fn(),
  sensorsList: vi.fn(),
  overview: vi.fn(),
  subscribe: vi.fn(),
  route: { name: 'rooms-dashboard', query: {} as Record<string, string> },
}))

vi.mock('@/services/api', () => ({
  roomsApi: { list: roomsList },
  plantsApi: { list: plantsList },
  sensorsApi: { list: sensorsList },
  dashboardApi: { overview },
  plantEventsApi: {},
  strainsApi: {},
  authApi: {},
  farmsApi: {},
}))

vi.mock('vue-router', () => ({
  useRoute: () => route,
  useRouter: () => ({ replace: vi.fn() }),
}))

vi.mock('@/composables/useMercure', () => ({
  useMercure: () => ({ subscribe, unsubscribe: vi.fn() }),
}))

enableAutoUnmount(afterEach)

function room(id: string, capacityMax: number, activePlantCount: number): Room {
  return { id, name: `Salle ${id}`, type: 'veg', farm: '/api/farms/farm-1', capacityMax, activePlantCount }
}

function collection(rooms: Room[], totalItems = rooms.length): HydraCollection<Room> {
  return { '@context': '/api/contexts/Room', '@id': '/api/rooms', '@type': 'hydra:Collection', 'hydra:member': rooms, 'hydra:totalItems': totalItems }
}

function mountDashboard() {
  return mount(RoomDashboard, {
    global: {
      stubs: {
        QBtn: { props: ['label'], template: '<button>{{ label }}<slot /></button>' },
        QCard: { template: '<section><slot /></section>' },
        QIcon: true,
        QSpinner: true,
        QSkeleton: true,
        QBtnToggle: true,
        QChip: { template: '<span><slot /></span>' },
        RoomForm: true,
        SensorForm: true,
        SensorThresholdsForm: true,
        SensorCard: true,
        RoomOverviewMetrics: true,
        RouterLink: true,
      },
    },
  })
}

describe('RoomDashboard room loading', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    setActivePinia(createPinia())
    route.name = 'rooms-dashboard'
    route.query = {}
    useAuthStore().user = {
      id: 'user-1', email: 'demo@cultivatrace.local', roles: ['ROLE_ORG_ADMIN'], mfaEnabled: false,
      organization: { id: 'org-1', name: 'CultivaTrace Demo', plan: 'pro', licenseStatus: 'active' },
    }
    roomsList.mockResolvedValue({ data: collection([room('A', 10, 3)]) })
    plantsList.mockResolvedValue({ data: { member: [], totalItems: 0 } })
    sensorsList.mockResolvedValue({ data: { member: [], totalItems: 0 } })
    const dashboard: DashboardOverviewResponse = {
      organization: { name: 'CultivaTrace Demo', plan: 'pro', licenseStatus: 'active' },
      plants: { byStage: { vegetation: 9 }, total: 9, inFlowering: 0 },
      harvests: { last30Days: 0, totalGrams: 0 },
      alerts: { sensorsInAlert: 0, saturatedRooms: 0, items: [] },
      rooms: { total: 2 },
      overview: { spotlightPlants: [], recentEvents: [] },
      limits: { plants: { current: 9, max: 200 } },
      generatedAt: '2026-11-05T12:00:00+00:00',
    }
    overview.mockResolvedValue({ data: dashboard })
  })

  afterEach(() => vi.restoreAllMocks())

  it('keeps cached rooms and indicators accessible during unrelated plant errors or loading', async () => {
    const store = usePlantsStore()
    await store.fetchRooms('org-1')
    roomsList.mockClear()
    store.error = 'Impossible de charger les evenements.'
    store.loading = true
    plantsList.mockRejectedValue(new Error('Plant endpoint unavailable'))

    const wrapper = mountDashboard()
    await flushPromises()

    expect(wrapper.get('.cultivation-overview__room-card h3').text()).toBe('Salle A')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.find('[aria-label="Chargement des salles"]').exists()).toBe(false)
    expect(wrapper.get('.cultivation-overview__metric-value').text()).toContain('/ 10 places')
    expect(overview).toHaveBeenCalledOnce()
    expect(roomsList).not.toHaveBeenCalled()
    expect(plantsList).not.toHaveBeenCalled()
    expect(store.error).toBe('Impossible de charger les evenements.')
    expect(store.loading).toBe(true)
  })

  it('waits for every room page before displaying facility occupancy and capacity', async () => {
    let resolveLastPage!: (value: { data: HydraCollection<Room> }) => void
    roomsList
      .mockResolvedValueOnce({ data: collection([room('A', 10, 3)], 2) })
      .mockImplementationOnce(() => new Promise(resolve => { resolveLastPage = resolve }))

    const wrapper = mountDashboard()
    await flushPromises()

    expect(wrapper.find('[aria-label="Chargement des salles"]').exists()).toBe(true)
    expect(wrapper.find('.cultivation-overview__metric-value').exists()).toBe(false)
    expect(wrapper.findAll('.cultivation-overview__room-card')).toHaveLength(0)

    resolveLastPage({ data: collection([room('B', 20, 6)], 2) })
    await flushPromises()

    expect(wrapper.find('[aria-label="Chargement des salles"]').exists()).toBe(false)
    expect(wrapper.get('.cultivation-overview__metric-value strong').text()).toBe('9')
    expect(wrapper.get('.cultivation-overview__metric-value span').text()).toBe('/ 30 places')
    expect(wrapper.get('[aria-label="Occupation des salles"]').attributes('aria-valuenow')).toBe('30')
    expect(wrapper.findAll('.cultivation-overview__room-card h3').map(node => node.text())).toEqual(['Salle A', 'Salle B'])
  })

  it('reports a room request failure and retries it on the next visit', async () => {
    vi.spyOn(console, 'error').mockImplementation(() => {})
    roomsList.mockRejectedValueOnce(new Error('Room endpoint unavailable'))
    const failed = mountDashboard()
    await flushPromises()

    expect(failed.get('[role="alert"]').text()).toContain('Impossible de charger les salles.')
    expect(failed.text()).not.toContain('Aucune salle configurée.')
    expect(failed.find('.cultivation-overview__metric-value').exists()).toBe(false)
    failed.unmount()

    const retried = mountDashboard()
    await flushPromises()

    expect(roomsList).toHaveBeenCalledTimes(2)
    expect(retried.find('[role="alert"]').exists()).toBe(false)
    expect(retried.get('.cultivation-overview__room-card h3').text()).toBe('Salle A')
  })

  it('refreshes room occupancy after a plant creation invalidates the room cache', async () => {
    const store = usePlantsStore()
    await store.fetchRooms('org-1')
    store.invalidateRoomOccupancy()
    roomsList.mockResolvedValueOnce({ data: collection([room('A', 10, 4)]) })

    const wrapper = mountDashboard()
    await flushPromises()

    expect(roomsList).toHaveBeenCalledTimes(2)
    expect(wrapper.get('.cultivation-overview__metric-value strong').text()).toBe('4')
  })

  it('preserves the sensors route fallback and room-filtered requests without global plant loading', async () => {
    route.name = 'sensors-dashboard'
    const store = usePlantsStore()
    await store.fetchRooms('org-1')
    store.loading = true
    store.error = 'Unrelated plant failure'

    const wrapper = mountDashboard()
    await flushPromises()

    expect(wrapper.get('h1').text()).toBe('Salle A')
    expect(wrapper.find('.room-skeleton').exists()).toBe(false)
    expect(plantsList).toHaveBeenCalledWith({ 'room.id': 'A', status: 'active', itemsPerPage: 100, page: 1 })
    expect(sensorsList).toHaveBeenCalledWith({ 'room.id': 'A' })
    expect(subscribe).toHaveBeenCalledWith(expect.stringContaining('/tenants/org-1/rooms/A'), expect.any(Function))
    expect(overview).not.toHaveBeenCalled()
  })
})
