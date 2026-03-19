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
      <q-chip
        v-if="currentRoom"
        square
        class="room-dashboard__room-chip"
      >
        {{ currentRoom.type }}
      </q-chip>
    </header>

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
      </c-card>

      <div v-if="currentRoomSensors.length" class="sensor-grid">
        <sensor-card
          v-for="sensor in currentRoomSensors"
          :key="sensor.id"
          :sensor="sensor"
          :live-reading="sensorsStore.getLiveReading(sensor.id)"
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
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import SensorCard from '@/components/sensors/SensorCard.vue'
import CCard from '@/components/ui/CCard.vue'
import { useMercure } from '@/composables/useMercure'
import { useAuthStore } from '@/stores/auth'
import { usePlantsStore } from '@/stores/plants'
import { useSensorsStore } from '@/stores/sensors'
import type { JwtPayload, Room, SensorVpdSnapshot } from '@/types/api'

const route = useRoute()
const plantsStore = usePlantsStore()
const sensorsStore = useSensorsStore()
const authStore = useAuthStore()
const { subscribe, unsubscribe } = useMercure()

const loading = computed(() => plantsStore.loading || sensorsStore.loading)

const fallbackRoomId = computed(() => plantsStore.rooms[0]?.id ?? null)

const currentRoomId = computed(() => {
  const routeRoomId = typeof route.query.roomId === 'string' ? route.query.roomId : null
  return routeRoomId ?? fallbackRoomId.value
})

const currentRoom = computed<Room | null>(() =>
  plantsStore.rooms.find((room) => room.id === currentRoomId.value) ?? null
)

const currentRoomSensors = computed(() => {
  const roomId = currentRoomId.value
  if (!roomId) {
    return []
  }

  return sensorsStore.sensorsByRoom.get(roomId) ?? []
})

const latestVpdReading = computed<SensorVpdSnapshot | null>(() => {
  for (const sensor of currentRoomSensors.value) {
    const vpd = sensorsStore.getLiveReading(sensor.id)?.vpd
    if (vpd) {
      return vpd
    }
  }

  return null
})

const vpdCard = computed(() => latestVpdReading.value)

const vpdTone = computed<'normal' | 'warning' | 'critical'>(() => {
  const rawStatus = vpdCard.value?.status?.toLowerCase() ?? ''
  if (rawStatus.includes('crit')) {
    return 'critical'
  }
  if (rawStatus.includes('warn') || rawStatus.includes('alert')) {
    return 'warning'
  }
  return 'normal'
})

const vpdLabel = computed(() => {
  const labels = {
    normal: 'Optimal',
    warning: 'Surveillance',
    critical: 'Action requise',
  }

  return labels[vpdTone.value]
})

const vpdValue = computed(() => {
  const value = vpdCard.value?.vpd
  return typeof value === 'number' ? `${value.toFixed(2)} kPa` : '--'
})

const orgId = computed(() => {
  if (typeof authStore.organization?.id === 'string' && authStore.organization.id.length) {
    return authStore.organization.id
  }

  const token = authStore.token
  if (!token) {
    return null
  }

  const payload = parseJwtPayload(token)

  if (typeof payload.orgId === 'string' && payload.orgId.length) {
    return payload.orgId
  }

  if (typeof payload.organizationId === 'string' && payload.organizationId.length) {
    return payload.organizationId
  }

  if (typeof payload.tenantId === 'string' && payload.tenantId.length) {
    return payload.tenantId
  }

  return null
})

const mercureTopic = computed(() => {
  if (!orgId.value || !currentRoomId.value) {
    return null
  }

  return `cannas/${orgId.value}/rooms/${currentRoomId.value}`
})

const subscriptionWarning = computed(() => {
  if (!currentRoomId.value) {
    return 'Impossible de determiner la salle a surveiller.'
  }

  if (!orgId.value) {
    return 'Impossible de determiner l organisation courante pour l abonnement Mercure.'
  }

  return null
})

function parseJwtPayload(token: string): JwtPayload & Record<string, unknown> {
  const [, rawPayload = ''] = token.split('.')
  const normalizedPayload = rawPayload
    .replace(/-/g, '+')
    .replace(/_/g, '/')
    .padEnd(Math.ceil(rawPayload.length / 4) * 4, '=')

  try {
    return JSON.parse(window.atob(normalizedPayload)) as JwtPayload & Record<string, unknown>
  } catch {
    return {}
  }
}

async function ensureRoomContext(): Promise<void> {
  if (!plantsStore.rooms.length || !plantsStore.plants.length) {
    await plantsStore.bootstrap()
  }

  if (currentRoomId.value) {
    await sensorsStore.fetchSensors(currentRoomId.value)
  }
}

async function syncSubscription(nextTopic: string | null, previousTopic: string | null): Promise<void> {
  if (previousTopic && previousTopic !== nextTopic) {
    unsubscribe(previousTopic)
  }

  if (!nextTopic) {
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
          }
        : null,
    })
  })
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

.vpd-card--normal {
  border-color: #2f855a;
  background: linear-gradient(135deg, #f0fff4, #ffffff);
}

.vpd-card--warning {
  border-color: #dd6b20;
  background: linear-gradient(135deg, #fffaf0, #ffffff);
}

.vpd-card--critical {
  border-color: #c53030;
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

.vpd-card__badge--normal {
  background: #c6f6d5;
  color: #22543d;
}

.vpd-card__badge--warning {
  background: #fbd38d;
  color: #9c4221;
}

.vpd-card__badge--critical {
  background: #feb2b2;
  color: #742a2a;
}

.vpd-card__message,
.empty-state p,
.warning-card p {
  margin: 0;
  color: #4a5568;
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
