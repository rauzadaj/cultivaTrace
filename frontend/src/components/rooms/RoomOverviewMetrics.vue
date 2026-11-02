<template>
  <section v-if="room" class="room-metrics" aria-label="Synthèse de la salle">
    <q-card flat class="room-metric">
      <div class="room-metric__top">
        <div>
          <p class="room-metric__label">Emprise & occupation</p>
          <p class="room-metric__caption">{{ room.name }} · plants actifs</p>
        </div>
        <div class="room-metric__gauge" role="progressbar" :aria-valuenow="occupancyPercent ?? undefined" aria-valuemin="0" aria-valuemax="100" aria-label="Occupation de la salle" :style="{ '--room-occupancy': (occupancyPercent ?? 0) + '%' }">
          <strong>{{ occupancyPercent === null ? '—' : occupancyPercent + '%' }}</strong>
        </div>
      </div>
      <div class="room-metric__value"><strong>{{ activePlantCount }}</strong><span>/ {{ room.capacityMax }} places</span></div>
      <div class="room-metric__progress" role="progressbar" :aria-valuenow="occupancyPercent ?? undefined" aria-valuemin="0" aria-valuemax="100">
        <span :style="{ width: (occupancyPercent ?? 0) + '%' }" />
      </div>
      <p class="room-metric__footnote">Capacité configurée pour cette salle</p>
    </q-card>

    <q-card flat class="room-metric">
      <div class="room-metric__top">
        <div><p class="room-metric__label">Répartition opérationnelle</p><p class="room-metric__caption">Plants actifs par stade</p></div>
        <q-icon name="mdi-sprout-outline" size="20px" class="room-metric__icon" aria-hidden="true" />
      </div>
      <div v-if="loadingPlants" class="room-metric__loading"><q-spinner color="primary" size="20px" /> Chargement des plants…</div>
      <p v-else-if="plantsError" class="room-metric__footnote" role="status">Répartition indisponible.</p>
      <div v-else-if="stageCounts.length" class="room-metric__stages">
        <div v-for="stage in stageCounts" :key="stage.id" class="room-metric__stage"><strong>{{ stage.count }}</strong><span>{{ stage.label }}</span></div>
      </div>
      <div v-else class="room-metric__stages"><div class="room-metric__stage"><strong>0</strong><span>Plants actifs</span></div></div>
      <p class="room-metric__footnote">{{ plantsError ? 'Les données de cette salle ne sont pas disponibles.' : activePlants.length === 1 ? '1 plant actif chargé pour cette salle' : activePlants.length + ' plants actifs chargés pour cette salle' }}</p>
    </q-card>

    <q-card flat class="room-metric">
      <div class="room-metric__top">
        <div><p class="room-metric__label">Capteurs de la salle</p><p class="room-metric__caption">État du dernier signal connu</p></div>
        <q-icon name="mdi-access-point" size="20px" class="room-metric__icon" aria-hidden="true" />
      </div>
      <div class="room-metric__value"><strong>{{ sensors.length }}</strong><span>{{ sensors.length === 1 ? 'capteur configuré' : 'capteurs configurés' }}</span></div>
      <div class="room-metric__sensor-status">
        <span class="room-metric__status-dot" :class="{ 'room-metric__status-dot--warning': offlineSensors > 0 }" />
        <span>{{ offlineSensors }} sans signal depuis 5 min</span>
      </div>
      <p class="room-metric__footnote">{{ sensors.length ? 'Selon les mesures et dates de signal disponibles' : 'Aucun capteur associé à cette salle' }}</p>
    </q-card>
  </section>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { Plant, Room, Sensor, SensorLiveReading, PlantStage } from '@/types/api'

const props = defineProps<{
  room: Room | null
  activePlants: Plant[]
  sensors: Sensor[]
  liveReading: (sensorId: string) => SensorLiveReading | null
  loadingPlants: boolean
  plantsError: boolean
}>()

const activePlantCount = computed(() => props.room?.activePlantCount ?? props.activePlants.length)
const occupancyPercent = computed<number | null>(() => {
  if (!props.room || props.room.capacityMax <= 0) return null
  const value = props.room.occupancyRate ?? (activePlantCount.value / props.room.capacityMax * 100)
  return Math.min(100, Math.max(0, Math.round(value)))
})
const stageLabels: Partial<Record<PlantStage, string>> = { germination: 'Germination', vegetation: 'Végétation', flowering: 'Floraison', harvest: 'Récolte' }
const stageCounts = computed(() => {
  const counts = new Map<PlantStage, number>()
  for (const plant of props.activePlants) counts.set(plant.stage, (counts.get(plant.stage) ?? 0) + 1)
  return Array.from(counts, ([id, count]) => ({ id, count, label: stageLabels[id] ?? 'Autre' }))
})
const offlineSensors = computed(() => props.sensors.filter((sensor) => {
  const timestamp = sensor.lastSeen ?? props.liveReading(sensor.id)?.recordedAt
  return !timestamp || Date.now() - new Date(timestamp).getTime() > 5 * 60 * 1000
}).length)
</script>

<style scoped>
.room-metrics { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--ct-space-3); }
.room-metric { display: grid; align-content: start; gap: var(--ct-space-3); min-width: 0; min-height: 166px; padding: var(--ct-space-4); border: 1px solid var(--ct-border-strong); border-radius: var(--ct-radius-md); background: var(--ct-surface); color: var(--ct-text); }
.room-metric__top { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--ct-space-2); }
.room-metric__label { margin: 0; color: var(--ct-secondary); font: var(--ct-text-body)/var(--ct-line-sm) var(--ct-font-sans); font-weight: var(--ct-weight-medium); }
.room-metric__caption, .room-metric__footnote { margin: var(--ct-space-1) 0 0; color: var(--ct-muted); font: 11px/var(--ct-line-sm) var(--ct-font-serif); }
.room-metric__gauge { position: relative; display: grid; width: 56px; height: 56px; flex: 0 0 56px; place-items: center; border-radius: 50%; background: conic-gradient(var(--ct-mint) var(--room-occupancy), var(--ct-raised) 0); }
.room-metric__gauge::before { content: ''; position: absolute; width: 44px; height: 44px; border-radius: 50%; background: var(--ct-surface); }
.room-metric__gauge strong { z-index: 1; color: var(--ct-text); font: 11px/1 var(--ct-font-sans); }
.room-metric__value { display: flex; align-items: baseline; gap: var(--ct-space-2); min-width: 0; }
.room-metric__value strong { color: var(--ct-text); font: 700 24px/1.2 var(--ct-font-sans); letter-spacing: var(--ct-tracking-heading); }
.room-metric__value span { color: var(--ct-muted); font: 12px/var(--ct-line-sm) var(--ct-font-serif); }
.room-metric__progress { height: 4px; overflow: hidden; background: var(--ct-raised); }
.room-metric__progress span { display: block; height: 100%; background: var(--ct-mint); transition: width 180ms ease; }
.room-metric__stages { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--ct-space-2); }
.room-metric__stage { display: grid; gap: var(--ct-space-1); min-width: 0; padding: var(--ct-space-2); background: var(--ct-inset); }
.room-metric__stage strong { color: var(--ct-text); font: 600 18px/1.2 var(--ct-font-sans); }
.room-metric__stage span { overflow: hidden; color: var(--ct-muted); font: 10px/var(--ct-line-xs) var(--ct-font-serif); text-overflow: ellipsis; white-space: nowrap; }
.room-metric__icon { color: var(--ct-mint); }
.room-metric__sensor-status { display: flex; align-items: center; gap: var(--ct-space-2); color: var(--ct-secondary); font: 12px/var(--ct-line-sm) var(--ct-font-serif); }
.room-metric__status-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--ct-mint); }
.room-metric__status-dot--warning { background: var(--ct-warning); }
.room-metric__loading { display: flex; align-items: center; gap: var(--ct-space-2); color: var(--ct-muted); font: 12px/var(--ct-line-sm) var(--ct-font-serif); }
@media (max-width: 760px) { .room-metrics { grid-template-columns: 1fr; } .room-metric { min-height: 150px; } }
</style>
