<template>
  <c-card class="sensor-card" :class="[`sensor-card--${cardStatus}`, { 'sensor-card--offline': isOffline }]">
    <div class="sensor-card__header">
      <div>
        <p class="sensor-card__eyebrow">{{ sensorTypeLabel }}</p>
        <h2 class="sensor-card__title">{{ sensor.deviceId }}</h2>
      </div>
      <div class="sensor-card__status-wrap">
        <span class="sensor-card__dot" :class="`sensor-card__dot--${cardStatus}`" />
        <q-chip dense square class="sensor-card__chip" :class="`sensor-card__chip--${cardStatus}`">
          {{ statusLabel }}
        </q-chip>
      </div>
    </div>

    <div v-if="liveReading" class="sensor-card__reading">
      <strong class="sensor-card__value">{{ formattedValue }}</strong>
      <span class="sensor-card__unit">{{ liveReading.unit }}</span>
    </div>
    <div v-else class="sensor-card__empty">
      <strong class="sensor-card__value sensor-card__value--muted">--</strong>
      <span class="sensor-card__unit">Aucune mesure</span>
    </div>

    <div class="sensor-card__meta">
      <span>{{ lastUpdateLabel }}</span>
      <span v-if="sensor.thresholds">{{ thresholdsLabel }}</span>
    </div>
  </c-card>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CCard from '@/components/ui/CCard.vue'
import type { Sensor, SensorLiveReading } from '@/types/api'

const props = defineProps<{
  sensor: Sensor
  liveReading: SensorLiveReading | null
}>()

const OFFLINE_THRESHOLD_MS = 5 * 60 * 1000

const sensorTypeLabel = computed(() => {
  const labels: Record<Sensor['type'], string> = {
    temperature: 'Temperature',
    humidity: 'Humidite',
    co2: 'CO2',
    ph: 'pH',
    ec: 'EC',
  }

  return labels[props.sensor.type] ?? props.sensor.type
})

const isOffline = computed(() => {
  const lastSeen = props.sensor.lastSeen ?? props.liveReading?.recordedAt
  if (!lastSeen) {
    return true
  }

  return Date.now() - new Date(lastSeen).getTime() > OFFLINE_THRESHOLD_MS
})

const cardStatus = computed<'normal' | 'warning' | 'critical'>(() => {
  if (isOffline.value) {
    return 'critical'
  }

  return props.liveReading?.status ?? 'normal'
})

const statusLabel = computed(() => {
  if (isOffline.value) {
    return 'Hors ligne'
  }

  const labels = {
    normal: 'Normal',
    warning: 'Attention',
    critical: 'Critique',
  }

  return labels[cardStatus.value]
})

const formattedValue = computed(() => {
  const value = props.liveReading?.value
  if (value === undefined) {
    return '--'
  }

  if (Math.abs(value) >= 100) {
    return value.toFixed(0)
  }

  if (Number.isInteger(value)) {
    return value.toString()
  }

  return value.toFixed(1)
})

const lastUpdateLabel = computed(() => {
  const timestamp = props.liveReading?.recordedAt ?? props.sensor.lastSeen
  if (!timestamp) {
    return 'Derniere mise a jour indisponible'
  }

  return `Maj ${new Intl.DateTimeFormat('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
    day: '2-digit',
    month: '2-digit',
  }).format(new Date(timestamp))}`
})

const thresholdsLabel = computed(() => {
  const thresholds = props.sensor.thresholds
  if (!thresholds) {
    return ''
  }

  return `${thresholds.min} - ${thresholds.max} ${thresholds.unit}`
})
</script>

<style scoped lang="scss">
.sensor-card {
  display: grid;
  gap: 16px;
  min-height: 220px;
  border: 2px solid #d9e2ec;
  transition: border-color 160ms ease, box-shadow 160ms ease;
}

.sensor-card--normal {
  border-color: #2f855a;
}

.sensor-card--warning {
  border-color: #dd6b20;
}

.sensor-card--critical,
.sensor-card--offline {
  border-color: #c53030;
}

.sensor-card__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.sensor-card__eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.sensor-card__title {
  margin: 0;
  font-size: 1.1rem;
  font-weight: 700;
  line-height: 1.2;
}

.sensor-card__status-wrap {
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 48px;
}

.sensor-card__dot {
  width: 14px;
  height: 14px;
  border-radius: 999px;
  flex-shrink: 0;
}

.sensor-card__dot--normal {
  background: #2f855a;
}

.sensor-card__dot--warning {
  background: #dd6b20;
}

.sensor-card__dot--critical {
  background: #c53030;
}

.sensor-card__chip {
  min-height: 48px;
  padding: 0 14px;
  font-weight: 700;
}

.sensor-card__chip--normal {
  background: #e6fffa;
  color: #22543d;
}

.sensor-card__chip--warning {
  background: #fff4e5;
  color: #9c4221;
}

.sensor-card__chip--critical {
  background: #fff5f5;
  color: #9b2c2c;
}

.sensor-card__reading,
.sensor-card__empty {
  display: flex;
  align-items: flex-end;
  gap: 10px;
  min-height: 72px;
}

.sensor-card__value {
  font-size: 32px;
  font-weight: 800;
  line-height: 1;
  letter-spacing: -0.04em;
}

.sensor-card__value--muted {
  color: #a0aec0;
}

.sensor-card__unit {
  padding-bottom: 4px;
  color: #4a5568;
  font-size: 1rem;
  font-weight: 600;
}

.sensor-card__meta {
  display: grid;
  gap: 8px;
  color: #718096;
  font-size: 0.9rem;
}
</style>
