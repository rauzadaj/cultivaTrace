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
        <q-btn
          flat
          round
          dense
          icon="mdi-pencil-outline"
          aria-label="Modifier seuils"
          class="sensor-card__edit-btn"
          @click="emit('edit-thresholds', sensor)"
        />
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

    <div v-if="sparklinePath" ref="chartRef" class="sensor-card__chart" @mouseleave="hoverIndex = null">
      <svg
        viewBox="0 0 200 44"
        preserveAspectRatio="none"
        class="sensor-card__svg"
        @mousemove="onChartHover"
      >
        <defs>
          <linearGradient :id="`sg-${sensor.id}`" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" :stop-color="sparkColor" stop-opacity="0.25" />
            <stop offset="100%" :stop-color="sparkColor" stop-opacity="0" />
          </linearGradient>
        </defs>
        <path :d="sparklineFill" :fill="`url(#sg-${sensor.id})`" />
        <path :d="sparklinePath" :stroke="sparkColor" stroke-width="2.5" fill="none" stroke-linecap="round" stroke-linejoin="round" />

        <template v-if="hoverIndex !== null">
          <line
            :x1="hoverSvgX" y1="0"
            :x2="hoverSvgX" y2="44"
            stroke="#718096" stroke-width="1" stroke-dasharray="3,2"
          />
          <circle
            :cx="hoverSvgX" :cy="hoverSvgY"
            r="3.5" :fill="sparkColor" stroke="white" stroke-width="2"
          />
        </template>
      </svg>

      <div v-if="hoverIndex !== null" class="sensor-card__tooltip" :style="tooltipStyle">
        <strong>{{ hoverValue }}</strong>
        <span>{{ hoverDate }}</span>
      </div>

      <div class="sensor-card__chart-labels">
        <span>-7j</span>
        <span>Aujourd'hui</span>
      </div>
    </div>

    <div class="sensor-card__meta">
      <span>{{ lastUpdateLabel }}</span>
      <span v-if="sensor.thresholds">{{ thresholdsLabel }}</span>
    </div>
  </c-card>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import CCard from '@/components/ui/CCard.vue'
import { sensorsApi } from '@/services/api'
import type { Sensor, SensorHistoryPoint, SensorLiveReading } from '@/types/api'

const props = defineProps<{
  sensor: Sensor
  liveReading: SensorLiveReading | null
}>()

const emit = defineEmits<{
  (event: 'edit-thresholds', sensor: Sensor): void
}>()

// ── Sparkline data ────────────────────────────────────────────────────────────

const sparkData = ref<SensorHistoryPoint[]>([])
const chartRef  = ref<HTMLElement | null>(null)
const hoverIndex = ref<number | null>(null)

onMounted(async () => {
  try {
    const { data } = await sensorsApi.readings(props.sensor.id, '7d')
    sparkData.value = data.data
  } catch {
    // sparkline is decorative — fail silently
  }
})

function svgY(value: number, pts: number[]): number {
  const min = Math.min(...pts)
  const max = Math.max(...pts)
  const range = max - min || 1
  const h = 44, pad = 4
  return h - pad - ((value - min) / range) * (h - pad * 2)
}

const sparklinePath = computed(() => {
  const pts = sparkData.value.map(p => p.avg_value)
  if (pts.length < 2) return ''
  return pts.map((v, i) => {
    const x = (i / (pts.length - 1)) * 200
    const y = svgY(v, pts)
    return `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`
  }).join(' ')
})

const sparklineFill = computed(() => {
  const path = sparklinePath.value
  return path ? `${path} L200,44 L0,44 Z` : ''
})

// ── Hover tooltip ─────────────────────────────────────────────────────────────

function onChartHover(e: MouseEvent) {
  const svg = e.currentTarget as SVGSVGElement
  const rect = svg.getBoundingClientRect()
  const ratio = (e.clientX - rect.left) / rect.width
  const idx = Math.round(ratio * (sparkData.value.length - 1))
  hoverIndex.value = Math.max(0, Math.min(idx, sparkData.value.length - 1))
}

const hoverSvgX = computed(() => {
  if (hoverIndex.value === null || sparkData.value.length < 2) return 0
  return (hoverIndex.value / (sparkData.value.length - 1)) * 200
})

const hoverSvgY = computed(() => {
  if (hoverIndex.value === null) return 0
  const pts = sparkData.value.map(p => p.avg_value)
  return svgY(pts[hoverIndex.value] ?? 0, pts)
})

const hoverValue = computed(() => {
  if (hoverIndex.value === null) return ''
  const pt = sparkData.value[hoverIndex.value]
  const unit = props.liveReading?.unit ?? ''
  return `${(pt?.avg_value ?? 0).toFixed(1)} ${unit}`
})

const hoverDate = computed(() => {
  if (hoverIndex.value === null) return ''
  const pt = sparkData.value[hoverIndex.value]
  if (!pt) return ''
  return new Date(pt.bucket).toLocaleString('fr-FR', {
    day: '2-digit', month: '2-digit',
    hour: '2-digit', minute: '2-digit',
  })
})

const tooltipStyle = computed(() => {
  if (hoverIndex.value === null || !chartRef.value) return {}
  const pct = hoverIndex.value / (sparkData.value.length - 1)
  const containerWidth = chartRef.value.getBoundingClientRect().width
  const tooltipWidth = 96
  const rawLeft = pct * containerWidth - tooltipWidth / 2
  const left = Math.max(0, Math.min(rawLeft, containerWidth - tooltipWidth))
  return { left: `${left}px` }
})

// ── Sensor card logic ─────────────────────────────────────────────────────────

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
  if (!lastSeen) return true
  return Date.now() - new Date(lastSeen).getTime() > OFFLINE_THRESHOLD_MS
})

const cardStatus = computed<'normal' | 'warning' | 'critical'>(() => {
  if (isOffline.value) return 'critical'
  return props.liveReading?.status ?? 'normal'
})

const sparkColor = computed(() => {
  const colors = { normal: '#22c55e', warning: '#f97316', critical: '#ef4444' }
  return colors[cardStatus.value] ?? '#22c55e'
})

const statusLabel = computed(() => {
  if (isOffline.value) return 'Hors ligne'
  return { normal: 'Normal', warning: 'Attention', critical: 'Critique' }[cardStatus.value]
})

const formattedValue = computed(() => {
  const value = props.liveReading?.value
  if (value === undefined) return '--'
  if (Math.abs(value) >= 100) return value.toFixed(0)
  if (Number.isInteger(value)) return value.toString()
  return value.toFixed(1)
})

const lastUpdateLabel = computed(() => {
  const timestamp = props.liveReading?.recordedAt ?? props.sensor.lastSeen
  if (!timestamp) return 'Derniere mise a jour indisponible'
  return `Maj ${new Intl.DateTimeFormat('fr-FR', {
    hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit',
  }).format(new Date(timestamp))}`
})

const thresholdsLabel = computed(() => {
  const t = props.sensor.thresholds
  return t ? `${t.min} - ${t.max} ${t.unit}` : ''
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

.sensor-card--normal  { border-color: #2f855a; }
.sensor-card--warning { border-color: #dd6b20; }
.sensor-card--critical,
.sensor-card--offline { border-color: #c53030; }

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

.sensor-card__dot--normal   { background: #2f855a; }
.sensor-card__dot--warning  { background: #dd6b20; }
.sensor-card__dot--critical { background: #c53030; }

.sensor-card__chip {
  min-height: 48px;
  padding: 0 14px;
  font-weight: 700;
}

.sensor-card__chip--normal   { background: #e6fffa; color: #22543d; }
.sensor-card__chip--warning  { background: #fff4e5; color: #9c4221; }
.sensor-card__chip--critical { background: #fff5f5; color: #9b2c2c; }

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

.sensor-card__value--muted { color: #a0aec0; }

.sensor-card__unit {
  padding-bottom: 4px;
  color: #4a5568;
  font-size: 1rem;
  font-weight: 600;
}

.sensor-card__chart {
  position: relative;
  display: grid;
  gap: 4px;
}

.sensor-card__svg {
  width: 100%;
  height: 56px;
  border-radius: 6px;
  overflow: hidden;
  cursor: crosshair;
  display: block;
}

.sensor-card__tooltip {
  position: absolute;
  top: -42px;
  width: 96px;
  padding: 5px 8px;
  border-radius: 8px;
  background: #1a202c;
  color: #fff;
  font-size: 0.78rem;
  display: flex;
  flex-direction: column;
  gap: 1px;
  pointer-events: none;
  z-index: 10;

  strong { font-size: 0.88rem; }
  span   { color: #a0aec0; font-size: 0.72rem; }

  &::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-bottom: none;
    border-top-color: #1a202c;
  }
}

.sensor-card__chart-labels {
  display: flex;
  justify-content: space-between;
  color: #a0aec0;
  font-size: 0.72rem;
}

.sensor-card__meta {
  display: grid;
  gap: 8px;
  color: #718096;
  font-size: 0.9rem;
}
</style>
