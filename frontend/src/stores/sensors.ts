/**
 * frontend/src/stores/sensors.ts
 *
 * Pinia store for IoT sensors and real-time readings.
 * Combines REST data (history) with Mercure SSE updates (real-time).
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { sensorsApi } from '@/services/api'
import type { HydraCollection, Sensor, SensorHistoryPoint, SensorLiveReading } from '@/types/api'

function collectionMembers<T>(collection: HydraCollection<T> | undefined | null): T[] {
  if (!collection) {
    return []
  }

  return collection['hydra:member'] ?? collection.member ?? []
}

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

export const useSensorsStore = defineStore('sensors', () => {
  const sensors       = ref<Sensor[]>([])
  const liveReadings  = ref<Map<string, SensorLiveReading>>(new Map())
  const history       = ref<Map<string, SensorHistoryPoint[]>>(new Map())
  const loading       = ref(false)
  const error         = ref<string | null>(null)

  // Sensors by room
  const sensorsByRoom = computed(() => {
    const map = new Map<string, Sensor[]>()
    sensors.value.forEach((sensor) => {
      const roomId = relationId(sensor.room)
      if (!roomId) {
        return
      }

      if (!map.has(roomId)) map.set(roomId, [])
      map.get(roomId)!.push(sensor)
    })
    return map
  })

  // Number of active alerts (sensors out of range)
  const alertCount = computed(() =>
    [...liveReadings.value.values()].filter((reading: SensorLiveReading) => reading.status !== 'normal').length
  )

  async function fetchSensors(roomId?: string): Promise<void> {
    loading.value = true
    error.value   = null
    try {
      const params = roomId ? { 'room.id': roomId } : {}
      const { data } = await sensorsApi.list(params)
      sensors.value = collectionMembers(data)
      await hydrateLatestReadings()
    } catch (e) {
      error.value = 'Unable to load sensors'
      sensors.value = []
      liveReadings.value = new Map()
    } finally {
      loading.value = false
    }
  }

  async function fetchHistory(sensorId: string, period: '7d' | '30d' | '90d' | '365d' = '30d'): Promise<void> {
    const { data } = await sensorsApi.readings(sensorId, period)
    history.value.set(sensorId, Array.isArray(data.data) ? data.data : [])
  }

  /**
   * Updates the real-time reading for a sensor.
   * Called by useMercure when an SSE update arrives.
   */
  function updateLiveReading(sensorId: string, update: {
    value:      number
    unit:       string
    recordedAt: string
    vpd?:       {
      vpd: number
      status: string
      message: string
      optimal_min: number
      optimal_max: number
      stage: string
    } | null
  }): void {
    const sensor = sensors.value.find((s: Sensor) => s.id === sensorId)
    if (!sensor) return

    const status = computeStatus(sensor, update.value)

    liveReadings.value.set(sensorId, {
      value:      update.value,
      unit:       update.unit,
      recordedAt: update.recordedAt,
      status,
      vpd:        update.vpd,
    })
  }

  function computeStatus(sensor: Sensor, value: number): 'normal' | 'warning' | 'critical' {
    if (!sensor.thresholds) return 'normal'
    const { min, max } = sensor.thresholds
    if (min !== undefined && value < min * 0.9) return 'critical'
    if (max !== undefined && value > max * 1.1) return 'critical'
    if (min !== undefined && value < min) return 'warning'
    if (max !== undefined && value > max) return 'warning'
    return 'normal'
  }

  function getLiveReading(sensorId: string): SensorLiveReading | null {
    return liveReadings.value.get(sensorId) ?? null
  }

  async function hydrateLatestReadings(): Promise<void> {
    if (!sensors.value.length) {
      liveReadings.value = new Map()
      return
    }

    const nextReadings = new Map(liveReadings.value)

    await Promise.all(
      sensors.value.map(async (sensor: Sensor) => {
        try {
          const { data } = await sensorsApi.readings(sensor.id, '30d')
          const points = Array.isArray(data.data) ? data.data : []
          history.value.set(sensor.id, points)

          const latestPoint = points[points.length - 1]
          if (!latestPoint) {
            return
          }

          const value = Number(latestPoint.avg_value)
          if (Number.isNaN(value)) {
            return
          }

          nextReadings.set(sensor.id, {
            value,
            unit: sensor.thresholds?.unit ?? '',
            recordedAt: latestPoint.bucket,
            status: computeStatus(sensor, value),
            vpd: null,
          })
        } catch {
          history.value.set(sensor.id, [])
        }
      }),
    )

    liveReadings.value = nextReadings
  }

  return {
    sensors, liveReadings, history, loading, error,
    sensorsByRoom, alertCount,
    fetchSensors, fetchHistory, updateLiveReading, getLiveReading,
  }
})
