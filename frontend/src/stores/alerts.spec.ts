import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'

const listMock = vi.fn()
const acknowledgeMock = vi.fn()

vi.mock('@/services/api', () => ({
  alertsApi: {
    list: listMock,
    acknowledge: acknowledgeMock,
  },
}))

describe('alerts store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    listMock.mockReset()
    acknowledgeMock.mockReset()
  })

  it('loads alerts and computes unread count', async () => {
    listMock.mockResolvedValue({
      data: {
        'hydra:member': [
          {
            id: 'a1',
            type: 'sensor_threshold',
            title: 'Room A',
            message: 'High temperature',
            severity: 'warning',
            createdAt: '2026-04-07T12:00:00+00:00',
            acknowledgedAt: null,
          },
          {
            id: 'a2',
            type: 'sensor_threshold',
            title: 'Room B',
            message: 'High CO2',
            severity: 'critical',
            createdAt: '2026-04-07T11:00:00+00:00',
            acknowledgedAt: '2026-04-07T11:30:00+00:00',
          },
        ],
      },
    })

    const { useAlertsStore } = await import('./alerts')
    const store = useAlertsStore()

    await store.fetchAlerts()

    expect(store.alerts).toHaveLength(2)
    expect(store.unreadCount).toBe(1)
  })

  it('updates an alert after acknowledge', async () => {
    const { useAlertsStore } = await import('./alerts')
    const store = useAlertsStore()
    store.alerts = [
      {
        id: 'a1',
        type: 'sensor_threshold',
        title: 'Room A',
        message: 'High temperature',
        severity: 'warning',
        createdAt: '2026-04-07T12:00:00+00:00',
        acknowledgedAt: null,
      },
    ]

    acknowledgeMock.mockResolvedValue({
      data: {
        id: 'a1',
        type: 'sensor_threshold',
        title: 'Room A',
        message: 'High temperature',
        severity: 'warning',
        createdAt: '2026-04-07T12:00:00+00:00',
        acknowledgedAt: '2026-04-07T12:05:00+00:00',
      },
    })

    await store.acknowledgeAlert('a1')

    expect(store.unreadCount).toBe(0)
    expect(store.alerts[0]?.acknowledgedAt).toBe('2026-04-07T12:05:00+00:00')
  })
})
