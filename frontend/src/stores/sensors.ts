/**
 * frontend/src/stores/sensors.ts
 *
 * Store Pinia pour les capteurs IoT et les lectures temps réel.
 * Combine les données REST (historique) avec les updates Mercure SSE (temps réel).
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { sensorsApi } from '@/services/api'
import type { Sensor, SensorLiveReading, SensorReading } from '@/types/api'

export const useSensorsStore = defineStore('sensors', () => {
  const sensors       = ref<Sensor[]>([])
  const liveReadings  = ref<Map<string, SensorLiveReading>>(new Map())
  const history       = ref<Map<string, SensorReading[]>>(new Map())
  const loading       = ref(false)
  const error         = ref<string | null>(null)

  // Capteurs par salle
  const sensorsByRoom = computed(() => {
    const map = new Map<string, Sensor[]>()
    sensors.value.forEach(s => {
      const roomId = s.room.id
      if (!map.has(roomId)) map.set(roomId, [])
      map.get(roomId)!.push(s)
    })
    return map
  })

  // Nombre d'alertes actives (capteurs hors seuil)
  const alertCount = computed(() =>
    [...liveReadings.value.values()].filter(r => r.status !== 'normal').length
  )

  async function fetchSensors(roomId?: string): Promise<void> {
    loading.value = true
    error.value   = null
    try {
      const params = roomId ? { 'room.id': roomId } : {}
      const { data } = await sensorsApi.list(params)
      sensors.value = data['hydra:member']
    } catch (e) {
      error.value = 'Impossible de charger les capteurs'
    } finally {
      loading.value = false
    }
  }

  async function fetchHistory(sensorId: string, period: '7d' | '30d' | '90d' | '365d' = '30d'): Promise<void> {
    const { data } = await sensorsApi.readings(sensorId, period)
    history.value.set(sensorId, data as unknown as SensorReading[])
  }

  /**
   * Met à jour la lecture temps réel d'un capteur.
   * Appelé par useMercure quand un update SSE arrive.
   */
  function updateLiveReading(sensorId: string, update: {
    value:      number
    unit:       string
    recordedAt: string
    vpd?:       { vpd: number; status: string; message: string } | null
  }): void {
    const sensor = sensors.value.find(s => s.id === sensorId)
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

  return {
    sensors, liveReadings, history, loading, error,
    sensorsByRoom, alertCount,
    fetchSensors, fetchHistory, updateLiveReading, getLiveReading,
  }
})
