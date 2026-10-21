import { defineComponent, ref } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils'
import { pinia } from '@/plugins/pinia'
import { useAuthStore } from '@/stores/auth'
import type { DashboardOverviewResponse } from '@/types/api'
import DashboardView from './DashboardView.vue'

const { overviewSpy } = vi.hoisted(() => ({ overviewSpy: vi.fn() }))
const mobile = ref(false)

vi.mock('@/services/api', () => ({
  dashboardApi: { overview: overviewSpy },
}))

vi.mock('@/composables/useDisplay', () => ({
  useDisplay: () => ({ xs: mobile }),
}))

enableAutoUnmount(afterEach)

const FarmFormStub = defineComponent({
  name: 'FarmForm',
  props: { modelValue: Boolean },
  emits: ['update:modelValue', 'created'],
  template: '<div />',
})
const PullToRefreshStub = defineComponent({
  name: 'QPullToRefresh',
  emits: ['refresh'],
  template: '<div><slot /></div>',
})
function buildOverview(): DashboardOverviewResponse {
  return {
    organization: { name: 'CultivaTrace Demo', plan: 'pro', licenseStatus: 'active' },
    plants: {
      byStage: { germination: 2, vegetation: 4, flowering: 3, harvest: 1 },
      total: 9,
      inFlowering: 3,
    },
    harvests: { last30Days: 1, totalGrams: 420 },
    alerts: {
      sensorsInAlert: 1,
      saturatedRooms: 1,
      items: [{
        id: 'room-capacity',
        title: 'Salle Floraison',
        message: '24/24 plants actifs',
        severity: 'warning',
        context: 'Capacité maximale atteinte',
      }],
    },
    rooms: { total: 3 },
    overview: {
      spotlightPlants: [{
        id: 'plant-1',
        name: 'RFID-001',
        strain: 'Gelato',
        room: 'Salle Floraison',
        stage: 'flowering',
        status: 'active',
        ageInDays: 42,
      }],
      recentEvents: [{
        id: 'event-1',
        eventType: 'note',
        notes: 'Observation récente',
        occurredAt: '2026-04-03T12:00:00+00:00',
        payload: null,
      }],
    },
    limits: { plants: { current: 9, max: 200 } },
    generatedAt: '2026-04-03T12:00:00+00:00',
  }
}

function mountDashboard() {
  return mount(DashboardView, {
    global: {
      plugins: [pinia],
      stubs: {
        'q-btn': {
          props: ['label', 'loading'],
          template: '<button :disabled="loading">{{ label }}<slot /></button>',
        },
        'q-skeleton': { template: '<div class="q-skeleton" />' },
        'q-pull-to-refresh': PullToRefreshStub,
        'q-icon': { template: '<i />' },
        'q-badge': { template: '<span><slot /></span>' },
        CCard: { template: '<section><slot /></section>' },
        FarmForm: FarmFormStub,
      },
    },
  })
}

describe('DashboardView', () => {
  beforeEach(() => {
    mobile.value = false
    overviewSpy.mockReset()
    const authStore = useAuthStore(pinia)
    authStore.token = 'jwt-token'
    authStore.user = {
      id: 'user-1',
      email: 'jonathan@cultivatrace.local',
      roles: ['ROLE_ORG_ADMIN'],
      mfaEnabled: false,
      organization: { id: 'org-1', name: 'CultivaTrace Demo', plan: 'pro', licenseStatus: 'active' },
    }
    overviewSpy.mockResolvedValue({ data: buildOverview() })
  })

  it('renders actual metrics, plants and events from the aggregated API payload', async () => {
    const wrapper = mountDashboard()
    await flushPromises()

    expect(overviewSpy).toHaveBeenCalledTimes(1)
    expect(wrapper.get('h1').text()).toBe('Tableau de bord exécutif')
    expect(wrapper.text()).toContain('Bonjour jonathan')
    expect(wrapper.findAll('.metric-card__value').map(item => item.text())).toEqual(['9', '3', '1', '1'])
    expect(wrapper.text()).toContain('Récoltes · 30 jours')
    expect(wrapper.text()).toContain('Salle Floraison')
    expect(wrapper.text()).toContain('Journal récent')
    expect(wrapper.text()).toContain('Observation récente')
    expect(wrapper.text()).toContain('RFID-001')
    expect(wrapper.text()).not.toContain('SHA-256')
    expect(wrapper.find('.priority-table').exists()).toBe(true)
  })

  it('computes stage shares from their own total rather than the active plant count', async () => {
    const wrapper = mountDashboard()
    await flushPromises()

    expect(wrapper.text()).toContain('10 plants répartis')
    expect(wrapper.get('[data-stage="germination"]').text()).toContain('20 %')
    expect(wrapper.get('[data-stage="vegetation"]').text()).toContain('40 %')
    expect(wrapper.get('[data-stage="flowering"]').text()).toContain('30 %')
    expect(wrapper.get('[data-stage="harvest"]').text()).toContain('10 %')
    expect(wrapper.text()).toContain('Germination')
    expect(wrapper.text()).toContain('Récolte')
  })

  it('shows loading placeholders without invented metrics until the API responds', async () => {
    let resolveOverview!: (value: { data: DashboardOverviewResponse }) => void
    overviewSpy.mockImplementationOnce(() => new Promise(resolve => { resolveOverview = resolve }))
    const wrapper = mountDashboard()

    expect(wrapper.find('[role="status"]').exists()).toBe(true)
    expect(wrapper.findAll('.metric-card')).toHaveLength(0)
    resolveOverview({ data: buildOverview() })
    await flushPromises()
    expect(wrapper.find('[role="status"]').exists()).toBe(false)
    expect(wrapper.findAll('.metric-card')).toHaveLength(4)
  })

  it('shows an explicit initial failure and allows retry without displaying zero metrics', async () => {
    overviewSpy.mockRejectedValueOnce(new Error('Réseau indisponible'))
    const wrapper = mountDashboard()
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Tableau de bord indisponible')
    expect(wrapper.get('[role="alert"]').text()).toContain('Réseau indisponible')
    expect(wrapper.findAll('.metric-card')).toHaveLength(0)
    await wrapper.get('[role="alert"] button').trigger('click')
    await flushPromises()

    expect(overviewSpy).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.findAll('.metric-card')).toHaveLength(4)
  })

  it('keeps the last successful metrics on refresh failure without counting the network error as an alert', async () => {
    const wrapper = mountDashboard()
    await flushPromises()
    overviewSpy.mockRejectedValueOnce(new Error('Actualisation interrompue'))

    await wrapper.get('.dashboard-refresh').trigger('click')
    await flushPromises()

    expect(wrapper.get('[role="alert"]').text()).toContain('Actualisation impossible')
    expect(wrapper.get('[role="alert"]').text()).toContain('Les dernières données reçues sont conservées')
    expect(wrapper.findAll('.metric-card__value').map(item => item.text())).toEqual(['9', '3', '1', '1'])
    expect(wrapper.text()).toContain('Observation récente')
  })

  it('preserves farm creation and refreshes the dashboard after a farm is created', async () => {
    const wrapper = mountDashboard()
    await flushPromises()
    const form = wrapper.getComponent(FarmFormStub)

    await wrapper.get('.dashboard-farm-btn').trigger('click')
    expect(form.props('modelValue')).toBe(true)
    form.vm.$emit('created')
    await flushPromises()
    expect(overviewSpy).toHaveBeenCalledTimes(2)
  })

  it('renders mobile plant cards and always completes pull-to-refresh on failure', async () => {
    mobile.value = true
    const wrapper = mountDashboard()
    await flushPromises()

    expect(wrapper.find('[data-testid="priority-cards"]').exists()).toBe(true)
    expect(wrapper.find('.priority-table').exists()).toBe(false)
    expect(wrapper.text()).toContain('Capacité maximale atteinte')
    expect(wrapper.text()).toContain('42 j')
    const done = vi.fn()
    overviewSpy.mockRejectedValueOnce(new Error('Hors ligne'))
    wrapper.getComponent(PullToRefreshStub).vm.$emit('refresh', done)
    await flushPromises()

    expect(done).toHaveBeenCalledTimes(1)
    expect(overviewSpy).toHaveBeenCalledTimes(2)
    expect(wrapper.find('[role="alert"]').exists()).toBe(true)
    expect(wrapper.text()).toContain('RFID-001')
  })

  it('distinguishes a successful empty response from a loading error', async () => {
    const empty = buildOverview()
    empty.plants = { total: 0, inFlowering: 0, byStage: {} }
    empty.rooms.total = 0
    empty.harvests = { last30Days: 0, totalGrams: 0 }
    empty.alerts = { sensorsInAlert: 0, saturatedRooms: 0, items: [] }
    empty.overview = { spotlightPlants: [], recentEvents: [] }
    overviewSpy.mockResolvedValueOnce({ data: empty })
    const wrapper = mountDashboard()
    await flushPromises()

    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
    expect(wrapper.findAll('.metric-card__value').map(item => item.text())).toEqual(['0', '0', '0', '0'])
    expect(wrapper.text()).toContain('Aucune alerte active')
    expect(wrapper.text()).toContain('Aucun plant à répartir')
    expect(wrapper.text()).toContain('Aucun événement récent')
    expect(wrapper.text()).toContain('Aucun plant prioritaire')
  })
})
