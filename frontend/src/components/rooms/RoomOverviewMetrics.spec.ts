import { afterEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import RoomOverviewMetrics from './RoomOverviewMetrics.vue'

describe('RoomOverviewMetrics', () => {
  afterEach(() => vi.useRealTimers())

  it('updates sensor connectivity as the five-minute threshold passes', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-11-02T15:00:00.000Z'))

    const wrapper = mount(RoomOverviewMetrics, {
      props: {
        room: { id: 'room-1', name: 'Salle test', type: 'veg', capacityMax: 10, farm: 'farm-1' },
        activePlants: [],
        sensors: [{ id: 'sensor-1', type: 'temperature', deviceId: 'device-1', protocol: 'mqtt', room: 'room-1', lastSeen: '2026-11-02T14:55:01.000Z' }],
        liveReading: () => null,
        loadingPlants: false,
        plantsError: false,
      },
      global: {
        stubs: {
          QCard: { template: '<div><slot /></div>' },
          QIcon: true,
          QSpinner: true,
        },
      },
    })

    expect(wrapper.text()).toContain('0 sans signal depuis 5 min')

    await vi.advanceTimersByTimeAsync(30_000)

    expect(wrapper.text()).toContain('1 sans signal depuis 5 min')
    wrapper.unmount()
    expect(vi.getTimerCount()).toBe(0)
  })
})
