import { ref } from 'vue'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { pinia } from '@/plugins/pinia'
import { useAuthStore } from '@/stores/auth'
import type { DashboardOverviewResponse } from '@/types/api'
import DashboardView from './DashboardView.vue'

const { overviewSpy } = vi.hoisted(() => ({
  overviewSpy: vi.fn(),
}))

vi.mock('@/services/api', () => ({
  dashboardApi: {
    overview: overviewSpy,
  },
}))

vi.mock('@/composables/useDisplay', () => ({
  useDisplay: () => ({
    xs: ref(false),
  }),
}))

function buildOverview(): DashboardOverviewResponse {
  return {
    organization: {
      name: 'CultivaTrace Demo',
      plan: 'pro',
      licenseStatus: 'active',
    },
    plants: {
      byStage: {
        germination: 2,
        vegetation: 4,
        flowering: 3,
        harvest: 1,
      },
      total: 9,
      inFlowering: 3,
    },
    harvests: {
      last30Days: 1,
      totalGrams: 420,
    },
    alerts: {
      sensorsInAlert: 1,
      saturatedRooms: 1,
      items: [
        {
          id: 'room-capacity',
          title: 'Salle Floraison',
          message: '24/24 plants actifs',
          severity: 'warning',
          context: 'Capacite maximale atteinte',
        },
      ],
    },
    rooms: {
      total: 3,
    },
    overview: {
      spotlightPlants: [
        {
          id: 'plant-1',
          name: 'RFID-001',
          strain: 'Gelato',
          room: 'Salle Floraison',
          stage: 'flowering',
          status: 'active',
          ageInDays: 42,
        },
      ],
      recentEvents: [
        {
          id: 'event-1',
          eventType: 'note',
          notes: 'Observation recente',
          occurredAt: '2026-04-03T12:00:00+00:00',
          payload: null,
        },
      ],
    },
    limits: {
      plants: {
        current: 9,
        max: 200,
      },
    },
    generatedAt: '2026-04-03T12:00:00+00:00',
  }
}

describe('DashboardView', () => {
  beforeEach(() => {
    const authStore = useAuthStore(pinia)
    authStore.token = 'jwt-token'
    authStore.user = {
      id: 'user-1',
      email: 'jonathan@cultivatrace.local',
      roles: ['ROLE_ORG_ADMIN'],
      mfaEnabled: false,
      organization: {
        id: 'org-1',
        name: 'CultivaTrace Demo',
        plan: 'pro',
        licenseStatus: 'active',
      },
    }
    overviewSpy.mockResolvedValue({ data: buildOverview() })
  })

  it('renders the key dashboard sections from the aggregated API payload', async () => {
    const wrapper = mount(DashboardView, {
      global: {
        plugins: [pinia],
        stubs: {
          'q-btn': { template: '<button><slot /></button>' },
          'q-skeleton': { template: '<div class="q-skeleton" />' },
          'q-pull-to-refresh': { template: '<div><slot /></div>' },
          'q-icon': { template: '<i />' },
          CCard: { template: '<section><slot /></section>' },
          AlertBadge: { template: '<span><slot /></span>' },
          PlantStageChip: { template: '<span><slot /></span>' },
          FarmForm: { template: '<div />' },
        },
      },
    })

    await flushPromises()

    expect(overviewSpy).toHaveBeenCalledTimes(1)
    expect(wrapper.text()).toContain('Bonjour jonathan')
    expect(wrapper.text()).toContain('Shift overview')
    expect(wrapper.text()).toContain('Plants actifs')
    expect(wrapper.text()).toContain('Salle Floraison')
    expect(wrapper.text()).toContain('Journal recent')
    expect(wrapper.text()).toContain('Observation recente')
    expect(wrapper.text()).toContain('Distribution active')
  })
})
