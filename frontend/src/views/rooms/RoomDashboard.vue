<template>
  <div class="room-dashboard">
    <header class="room-dashboard__header">
      <div>
        <p class="section-eyebrow">IoT Room</p>
        <h1>{{ currentRoom?.name ?? 'Dashboard temps reel' }}</h1>
        <p class="room-dashboard__subtitle">
          {{ currentRoom ? `Surveillance live de la salle ${currentRoom.name}` : 'Aucune salle selectionnee' }}
        </p>
      </div>
      <div class="room-dashboard__header-actions">
        <q-btn
          v-if="route.name === 'rooms-dashboard'"
          color="primary"
          icon="mdi-door-plus"
          label="Nouvelle salle"
          no-caps
          class="room-dashboard__action-btn"
          @click="roomDialogOpen = true"
        />
        <q-btn
          v-if="route.name === 'sensors-dashboard'"
          color="primary"
          icon="mdi-thermometer-plus"
          label="Nouveau capteur"
          no-caps
          class="room-dashboard__action-btn"
          @click="sensorDialogOpen = true"
        />
        <q-chip
          v-if="currentRoom"
          square
          class="room-dashboard__room-chip"
        >
          {{ currentRoom.type }}
        </q-chip>
      </div>
    </header>

    <div v-if="roomTabs.length > 1" class="room-dashboard__switcher">
      <q-btn
        v-for="room in roomTabs"
        :key="room.id"
        unelevated
        no-caps
        class="room-dashboard__switch-btn"
        :class="{ 'room-dashboard__switch-btn--active': room.id === currentRoomId }"
        @click="selectRoom(room.id)"
      >
        <span>{{ room.name }}</span>
        <small>{{ room.type }}</small>
      </q-btn>
    </div>

    <div v-if="loading" class="room-skeleton">
      <q-skeleton v-for="index in 4" :key="index" height="220px" class="room-skeleton__item" />
    </div>

    <template v-else>
      <c-card v-if="vpdCard" class="vpd-card" :class="`vpd-card--${vpdTone}`">
        <div class="vpd-card__header">
          <div>
            <p class="section-eyebrow">VPD</p>
            <h2>{{ vpdValue }}</h2>
          </div>
          <div class="vpd-card__badge" :class="`vpd-card__badge--${vpdTone}`">
            {{ vpdLabel }}
          </div>
        </div>
        <p class="vpd-card__message">{{ vpdCard.message }}</p>
        <div class="vpd-card__meta">
          <span>Plage optimale {{ vpdRange }}</span>
          <span>Stade {{ vpdStageLabel }}</span>
        </div>
      </c-card>

      <div v-if="currentRoomSensors.length" class="sensor-grid">
        <sensor-card
          v-for="sensor in currentRoomSensors"
          :key="sensor.id"
          :sensor="sensor"
          :live-reading="sensorsStore.getLiveReading(sensor.id)"
          @edit-thresholds="openThresholdsDialog"
        />
      </div>

      <c-card v-else class="empty-state">
        <p class="section-eyebrow">Capteurs</p>
        <h2>Aucun capteur dans cette salle</h2>
        <p>Le dashboard temps reel affichera les mesures ici des qu'un capteur IoT sera associe a la salle.</p>
      </c-card>

      <c-card v-if="subscriptionWarning" class="warning-card">
        <p class="section-eyebrow">Mercure</p>
        <p>{{ subscriptionWarning }}</p>
      </c-card>
    </template>


    <room-form v-model="roomDialogOpen" @created="handleRoomCreated" />
    <sensor-form v-model="sensorDialogOpen" @created="handleSensorCreated" />
    <sensor-thresholds-form
      v-model="thresholdDialogOpen"
      :sensor="editingSensor"
      @saved="handleThresholdSaved"
    />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import RoomForm from '@/components/rooms/RoomForm.vue'
import SensorCard from '@/components/sensors/SensorCard.vue'
import SensorForm from '@/components/sensors/SensorForm.vue'
import SensorThresholdsForm from '@/components/sensors/SensorThresholdsForm.vue'
import CCard from '@/components/ui/CCard.vue'
import { useMercure } from '@/composables/useMercure'
import { useAuthStore } from '@/stores/auth'
import { usePlantsStore } from '@/stores/plants'
import { useSensorsStore } from '@/stores/sensors'
import type { Room, Sensor, SensorVpdSnapshot } from '@/types/api'

const route = useRoute()
const router = useRouter()
const plantsStore = usePlantsStore()
const sensorsStore = useSensorsStore()
const authStore = useAuthStore()
const { subscribe, unsubscribe } = useMercure()
const roomDialogOpen = ref(false)
const sensorDialogOpen = ref(false)
const thresholdDialogOpen = ref(false)
const editingSensor = ref<Sensor | null>(null)

const loading = computed(() => plantsStore.loading || sensorsStore.loading)

const fallbackRoomId = computed(() => plantsStore.rooms[0]?.id ?? null)

const currentRoomId = computed(() => {
  const routeRoomId = typeof route.query.roomId === 'string' ? route.query.roomId : null
  return routeRoomId ?? fallbackRoomId.value
})

const currentRoom = computed<Room | null>(() =>
  plantsStore.rooms.find((room) => room.id === currentRoomId.value) ?? null
)

const roomTabs = computed(() => plantsStore.rooms.map((room) => ({
  id: room.id,
  name: room.name,
  type: room.type,
})))

const currentRoomSensors = computed(() => {
  const roomId = currentRoomId.value
  if (!roomId) {
    return []
  }

  return sensorsStore.sensorsByRoom.get(roomId) ?? []
})

function computeVpdValue(temperatureCelsius: number, humidityPercent: number): number {
  const svp = 0.6108 * Math.exp((17.27 * temperatureCelsius) / (temperatureCelsius + 237.3))
  return Math.round(svp * (1 - humidityPercent / 100) * 100) / 100
}

function evaluateVpdSnapshot(value: number, stage: 'germination' | 'vegetation' | 'flowering'): SensorVpdSnapshot {
  const ranges: Record<'germination' | 'vegetation' | 'flowering', [number, number]> = {
    germination: [0.4, 0.8],
    vegetation: [0.8, 1.2],
    flowering: [1.0, 1.5],
  }

  const [optimalMin, optimalMax] = ranges[stage]

  if (value < optimalMin) {
    return {
      vpd: value,
      status: 'too_low',
      message: `VPD trop faible (${value.toFixed(2)} kPa) — Risque d'exces d'humidite, fonte des semis, botrytis`,
      optimal_min: optimalMin,
      optimal_max: optimalMax,
      stage,
    }
  }

  if (value > optimalMax) {
    return {
      vpd: value,
      status: 'too_high',
      message: `VPD trop eleve (${value.toFixed(2)} kPa) — Stress hydrique, fermeture des stomates, ralentissement de croissance`,
      optimal_min: optimalMin,
      optimal_max: optimalMax,
      stage,
    }
  }

  return {
    vpd: value,
    status: 'optimal',
    message: `VPD optimal (${value.toFixed(2)} kPa) — Transpiration et croissance optimales`,
    optimal_min: optimalMin,
    optimal_max: optimalMax,
    stage,
  }
}

function inferStageFromRoom(room: Room | null): 'germination' | 'vegetation' | 'flowering' {
  if (room?.type === 'flower') {
    return 'flowering'
  }

  return 'vegetation'
}

const derivedVpdReading = computed<SensorVpdSnapshot | null>(() => {
  let temperature: number | null = null
  let humidity: number | null = null

  for (const sensor of currentRoomSensors.value) {
    const liveReading = sensorsStore.getLiveReading(sensor.id)
    if (!liveReading) {
      continue
    }

    if (sensor.type === 'temperature') {
      temperature = liveReading.value
    }

    if (sensor.type === 'humidity') {
      humidity = liveReading.value
    }
  }

  if (temperature === null || humidity === null) {
    return null
  }

  return evaluateVpdSnapshot(
    computeVpdValue(temperature, humidity),
    inferStageFromRoom(currentRoom.value),
  )
})

const latestVpdReading = computed<SensorVpdSnapshot | null>(() => {
  for (const sensor of currentRoomSensors.value) {
    const vpd = sensorsStore.getLiveReading(sensor.id)?.vpd
    if (vpd) {
      return vpd
    }
  }

  return derivedVpdReading.value
})

const vpdCard = computed(() => latestVpdReading.value)

const vpdTone = computed<'optimal' | 'too_low' | 'too_high'>(() => {
  const rawStatus = vpdCard.value?.status?.toLowerCase() ?? ''
  if (rawStatus === 'too_low') {
    return 'too_low'
  }
  if (rawStatus === 'too_high') {
    return 'too_high'
  }
  return 'optimal'
})

const vpdLabel = computed(() => {
  const labels = {
    optimal: 'Optimal',
    too_low: 'Trop bas',
    too_high: 'Trop haut',
  }

  return labels[vpdTone.value]
})

const vpdValue = computed(() => {
  const value = vpdCard.value?.vpd
  return typeof value === 'number' ? `${value.toFixed(2)} kPa` : '--'
})

const vpdRange = computed(() => {
  if (!vpdCard.value) {
    return '--'
  }

  return `${vpdCard.value.optimal_min.toFixed(1)} – ${vpdCard.value.optimal_max.toFixed(1)} kPa`
})

const vpdStageLabel = computed(() => {
  const stage = vpdCard.value?.stage ?? ''
  const stageLabels: Record<string, string> = {
    germination: 'Germination',
    vegetation: 'Vegetation',
    flowering: 'Floraison',
  }

  return stageLabels[stage] ?? (stage || 'Inconnu')
})

const orgId = computed(() => authStore.organization?.id ?? null)

const mercureTopic = computed(() => {
  if (!orgId.value || !currentRoomId.value) {
    return null
  }

  return `https://cultivatrace.com/tenants/${orgId.value}/rooms/${currentRoomId.value}`
})

const subscriptionWarning = computed(() => {
  if (!currentRoomId.value) {
    return 'Impossible de determiner la salle a surveiller.'
  }

  return null
})

async function ensureRoomContext(): Promise<void> {
  if (!orgId.value) {
    try {
      await authStore.fetchMe()
    } catch (error) {
      console.error('Unable to resolve current organization for Mercure', error)
    }
  }

  if (!plantsStore.rooms.length || !plantsStore.plants.length) {
    await Promise.all([plantsStore.fetchRooms(), plantsStore.fetchPlants()])
  }

  if (currentRoomId.value) {
    await sensorsStore.fetchSensors(currentRoomId.value)
  }
}

function selectRoom(roomId: string): void {
  if (roomId === currentRoomId.value) {
    return
  }

  void router.replace({
    query: {
      ...route.query,
      roomId,
    },
  })
}

async function syncSubscription(nextTopic: string | null, previousTopic: string | null): Promise<void> {
  if (previousTopic && previousTopic !== nextTopic) {
    unsubscribe(previousTopic)
  }

  if (!nextTopic) {
    if (!orgId.value) {
      console.error('Mercure subscription skipped: missing organization id')
    }

    return
  }

  subscribe(nextTopic, (update) => {
    sensorsStore.updateLiveReading(update.sensorId, {
      value: update.value,
      unit: update.unit,
      recordedAt: update.recordedAt,
      vpd: update.vpd
        ? {
            vpd: update.vpd.vpd,
            status: update.vpd.status,
            message: update.vpd.message,
            optimal_min: update.vpd.optimal_min,
            optimal_max: update.vpd.optimal_max,
            stage: update.vpd.stage,
          }
        : null,
    })
  })
}


function openThresholdsDialog(sensor: Sensor): void {
  editingSensor.value = sensor
  thresholdDialogOpen.value = true
}

async function handleRoomCreated(): Promise<void> {
  await plantsStore.fetchRooms()
}

async function handleSensorCreated(): Promise<void> {
  if (currentRoomId.value) {
    await sensorsStore.fetchSensors(currentRoomId.value)
  }
}

function handleThresholdSaved(sensor: Sensor): void {
  const index = sensorsStore.sensors.findIndex((item) => item.id === sensor.id)
  if (index !== -1) {
    sensorsStore.sensors[index] = sensor
  }
}

onMounted(async () => {
  try {
    await ensureRoomContext()
    await syncSubscription(mercureTopic.value, null)
  } catch (error) {
    console.error('Room dashboard bootstrap failed', error)
  }
})

watch(currentRoomId, async (nextRoomId, previousRoomId) => {
  if (!nextRoomId || nextRoomId === previousRoomId) {
    return
  }

  try {
    await sensorsStore.fetchSensors(nextRoomId)
  } catch (error) {
    console.error('Sensor fetch failed', error)
  }
})

watch(mercureTopic, async (nextTopic, previousTopic) => {
  await syncSubscription(nextTopic, previousTopic)
})
</script>

<style scoped lang="scss">
@use '../../css/breakpoints.sass' as bp;

.room-dashboard {
  display: grid;
  gap: 16px;
  height: auto;
  min-height: auto;
  overflow-y: visible;
  padding-bottom: calc(24px + env(safe-area-inset-bottom));
}

.room-dashboard__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.room-dashboard__header-actions {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
  justify-content: flex-end;
}

.room-dashboard__action-btn {
  min-height: 48px;
}

.room-dashboard__switcher {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.room-dashboard__switch-btn {
  display: grid;
  justify-items: start;
  gap: 2px;
  min-height: 48px;
  padding: 10px 14px;
  border-radius: 12px;
  background: #edf2f7;
  color: #4a5568;
}

.room-dashboard__switch-btn--active {
  background: #e8f5ee;
  color: #1b6b3a;
}

.room-dashboard__switch-btn small {
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.08em;
}

.room-dashboard__header h1 {
  margin: 0;
  font-size: 1.75rem;
  font-weight: 700;
  line-height: 1.1;
}

.room-dashboard__subtitle {
  margin: 8px 0 0;
  color: #718096;
}

.room-dashboard__room-chip {
  min-height: 40px;
  padding: 0 12px;
  text-transform: uppercase;
}

.section-eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.room-skeleton {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.room-skeleton__item {
  border-radius: 16px;
}

.sensor-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.vpd-card {
  display: grid;
  gap: 12px;
  border: 2px solid #d9e2ec;
}

.vpd-card--optimal {
  border-color: #2f855a;
  background: linear-gradient(135deg, #f0fff4, #ffffff);
}

.vpd-card--too_low {
  border-color: #2b6cb0;
  background: linear-gradient(135deg, #ebf8ff, #ffffff);
}

.vpd-card--too_high {
  border-color: #dd6b20;
  background: linear-gradient(135deg, #fff5f5, #ffffff);
}

.vpd-card__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.vpd-card__header h2 {
  margin: 0;
  font-size: 2rem;
  font-weight: 800;
}

.vpd-card__badge {
  min-height: 48px;
  padding: 12px 16px;
  border-radius: 12px;
  font-size: 0.9rem;
  font-weight: 700;
}

.vpd-card__badge--optimal {
  background: #c6f6d5;
  color: #22543d;
}

.vpd-card__badge--too_low {
  background: #bee3f8;
  color: #1a365d;
}

.vpd-card__badge--too_high {
  background: #fbd38d;
  color: #9c4221;
}

.vpd-card__message,
.empty-state p,
.warning-card p {
  margin: 0;
  color: #4a5568;
}

.vpd-card__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  color: #718096;
  font-size: 0.95rem;
}

.empty-state h2 {
  margin: 0 0 8px;
  font-size: 1.25rem;
}

.warning-card {
  border: 2px solid #f6ad55;
  background: #fffaf0;
}

@include bp.mobile {
  .room-dashboard {
    padding-bottom: calc(88px + env(safe-area-inset-bottom));
  }

  .room-dashboard__header {
    flex-direction: column;
  }
}

@include bp.mobile-wide {
  .sensor-grid,
  .room-skeleton {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@include bp.tablet {
  .sensor-grid,
  .room-skeleton {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@include bp.desktop {
  .room-dashboard {
    padding-bottom: 32px;
  }

  .sensor-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
