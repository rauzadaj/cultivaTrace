<template>
  <div class="room-dashboard">
    <header class="room-dashboard__header">
      <div>
        <p class="section-eyebrow">Rooms</p>
        <h1>Surveillance environnementale</h1>
      </div>
    </header>

    <div v-if="plantsStore.loading" class="room-skeleton">
      <q-skeleton v-for="index in 4" :key="index" height="168px" class="room-skeleton__item" />
    </div>

    <q-pull-to-refresh v-if="!isDesktop" @refresh="refreshRooms">
      <div v-if="!plantsStore.loading" class="sensor-grid">
        <c-card
          v-for="metric in sensorMetrics"
          :key="metric.id"
          class="sensor-card"
          :class="`sensor-card--${metric.status}`"
        >
          <span class="sensor-card__label">{{ metric.label }}</span>
          <strong>{{ metric.value }}<small>{{ metric.unit }}</small></strong>
          <p>{{ metric.detail }}</p>
        </c-card>
      </div>
    </q-pull-to-refresh>

    <div v-else-if="!plantsStore.loading" class="sensor-grid">
      <c-card
        v-for="metric in sensorMetrics"
        :key="metric.id"
        class="sensor-card"
        :class="`sensor-card--${metric.status}`"
      >
        <span class="sensor-card__label">{{ metric.label }}</span>
        <strong>{{ metric.value }}<small>{{ metric.unit }}</small></strong>
        <p>{{ metric.detail }}</p>
      </c-card>
    </div>

    <div v-if="isDesktop" class="history-grid">
      <c-card v-for="room in roomCards" :key="room.id">
        <p class="section-eyebrow">{{ room.type }}</p>
        <h2>{{ room.name }}</h2>
        <div class="history-chart">
          <div
            v-for="bar in 12"
            :key="bar"
            class="history-chart__bar"
            :style="{ height: `${Math.max(24, Math.round((room.occupancyRate / 100) * 140) - ((bar % 3) * 8))}px` }"
          />
        </div>
        <p class="sensor-card__detail">{{ room.count }}/{{ room.capacityMax }} plants actifs</p>
      </c-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import CCard from '../../components/ui/CCard.vue'
import { useDisplay } from '../../composables/useDisplay'
import { usePlantsStore } from '../../stores/plants'
import type { SensorMetricCard } from '../../types/api'

const plantsStore = usePlantsStore()
const { desktop } = useDisplay()

const isDesktop = computed(() => desktop.value)

const roomCards = computed(() => plantsStore.rooms.map((room) => {
  const count = plantsStore.plants.filter((plant) => {
    if (typeof plant.room === 'string') {
      return plant.room.endsWith(`/${room.id}`)
    }

    return plant.room.id === room.id
  }).length

  return {
    id: room.id,
    name: room.name,
    type: room.type,
    capacityMax: room.capacityMax,
    count,
    occupancyRate: room.capacityMax ? Math.min(100, Math.round((count / room.capacityMax) * 100)) : 0,
  }
}))

const totalCapacity = computed(() => roomCards.value.reduce((sum, room) => sum + room.capacityMax, 0))
const totalPlants = computed(() => roomCards.value.reduce((sum, room) => sum + room.count, 0))

const sensorMetrics = computed<SensorMetricCard[]>(() => [
  {
    id: 'rooms',
    label: 'Salles actives',
    value: String(plantsStore.rooms.length),
    unit: '',
    status: 'ok',
    detail: 'Espaces exploites par le tenant',
  },
  {
    id: 'plants',
    label: 'Plants actifs',
    value: String(plantsStore.activePlants.length),
    unit: '',
    status: 'ok',
    detail: 'Plants suivis en temps reel',
  },
  {
    id: 'occupancy',
    label: 'Occupation',
    value: totalCapacity.value ? String(Math.round((totalPlants.value / totalCapacity.value) * 100)) : '0',
    unit: '%',
    status: totalCapacity.value && totalPlants.value >= totalCapacity.value ? 'warning' : 'ok',
    detail: `${totalPlants.value}/${totalCapacity.value} emplacements utilises`,
  },
  {
    id: 'flower',
    label: 'Salle flowering',
    value: String(roomCards.value.filter((room) => room.type === 'flower').reduce((sum, room) => sum + room.count, 0)),
    unit: '',
    status: 'ok',
    detail: 'Plants actuellement en floraison ou assignes a cette salle',
  },
])

async function refreshRooms(done: () => void) {
  try {
    await plantsStore.bootstrap()
  } catch (error) {
    console.error('Room refresh failed', error)
  }
  done()
}

onMounted(async () => {
  if (!plantsStore.rooms.length || !plantsStore.plants.length) {
    try {
      await plantsStore.bootstrap()
    } catch (error) {
      console.error('Room dashboard bootstrap failed', error)
    }
  }
})
</script>

<style scoped lang="scss">
@use '../../css/breakpoints.sass' as bp;
.room-dashboard { display: grid; gap: 16px; }
.room-skeleton { display: grid; grid-template-columns: 1fr; gap: 12px; }
.room-skeleton__item { border-radius: 16px; }
.room-dashboard__header h1 { margin: 0; font-size: 1.5rem; font-weight: 600; line-height: 1.15; }
.section-eyebrow { margin: 0 0 6px; color: #718096; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.12em; }
.sensor-grid { display: grid; grid-template-columns: 1fr; gap: 12px; }
.sensor-card { display: grid; gap: 8px; }
.sensor-card__label { color: #718096; font-size: 0.875rem; }
.sensor-card strong { font-size: 2rem; font-weight: 700; line-height: 1; }
.sensor-card strong small { margin-left: 6px; font-size: 1rem; }
.sensor-card p, .sensor-card__detail { margin: 0; color: #718096; }
.sensor-card--warning { background: #fff7e8; }
.sensor-card--critical { background: #fff0f0; animation: room-alert-pulse 1.6s ease-in-out infinite; }
.history-grid { display: grid; grid-template-columns: 1fr; gap: 16px; }
.history-grid h2 { margin: 0 0 14px; font-size: 1.25rem; font-weight: 600; }
.history-chart { display: grid; grid-template-columns: repeat(12, minmax(0, 1fr)); gap: 8px; align-items: end; min-height: 160px; }
.history-chart__bar { border-radius: 999px 999px 6px 6px; background: linear-gradient(180deg, #2d9e5f, #1b6b3a); }
@keyframes room-alert-pulse { 0%,100%{ box-shadow: 0 0 0 0 rgba(197,48,48,0.15);} 50%{ box-shadow: 0 0 0 10px rgba(197,48,48,0);} }
@include bp.tablet { .room-dashboard__header h1 { font-size: 2rem; } .room-skeleton, .sensor-grid, .history-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@include bp.mobile { .room-dashboard__header h1 { font-size: 1.5rem; } .sensor-card strong { font-size: 1.75rem; } }
@include bp.mobile-wide { .room-skeleton, .sensor-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@include bp.desktop { .room-dashboard__header h1 { font-size: 2rem; } .history-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
</style>
