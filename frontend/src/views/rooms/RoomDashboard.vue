<template>
  <div class="room-dashboard">
    <header class="room-dashboard__header">
      <div>
        <p class="section-eyebrow">Rooms</p>
        <h1>Surveillance environnementale</h1>
      </div>
    </header>

    <div class="sensor-grid">
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
      <c-card v-for="chart in historyCards" :key="chart.id">
        <p class="section-eyebrow">{{ chart.label }}</p>
        <h2>{{ chart.title }}</h2>
        <div class="history-chart">
          <div v-for="bar in 12" :key="bar" class="history-chart__bar" :style="{ height: `${32 + (bar % 6) * 10}px` }" />
        </div>
      </c-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import CCard from '../../components/ui/CCard.vue'
import { useDisplay } from '../../composables/useDisplay'
import { useCropStore } from '../../stores/useCropStore'
import type { SensorMetricCard } from '../../types/api'

const cropStore = useCropStore()
const { desktop } = useDisplay()

const isDesktop = computed(() => desktop.value)
const latestPh = computed(() => cropStore.latestEntries.find((entry) => typeof entry.phLevel?.value === 'number')?.phLevel?.value ?? 6.2)
const latestPpm = computed(() => cropStore.latestEntries.find((entry) => typeof entry.nutrientConcentration?.ppm === 'number')?.nutrientConcentration?.ppm ?? 912)

const sensorMetrics = computed<SensorMetricCard[]>(() => [
  { id: 'temp', label: 'Temperature', value: '24.8', unit: '°C', status: 'ok', detail: 'Salle veg nord' },
  { id: 'humidity', label: 'Humidity', value: '59', unit: '%', status: 'ok', detail: 'Zone stable' },
  { id: 'ph', label: 'pH', value: latestPh.value.toFixed(2), unit: '', status: latestPh.value > 6.6 ? 'warning' : 'ok', detail: 'Solution racinaire' },
  { id: 'ec', label: 'EC', value: String(latestPpm.value), unit: 'ppm', status: latestPpm.value > 1100 ? 'critical' : 'ok', detail: 'Nutriments en circulation' },
])

const historyCards = [
  { id: 'climate', label: 'Historique', title: 'Temperature et humidite' },
  { id: 'nutrients', label: 'Historique', title: 'pH et EC' },
] as const
</script>

<style scoped>
.room-dashboard {
  display: grid;
  gap: 16px;
}

.room-dashboard__header h1 {
  margin: 0;
  font-size: 2rem;
  font-weight: 600;
}

.section-eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.sensor-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.sensor-card {
  display: grid;
  gap: 8px;
}

.sensor-card__label {
  color: #718096;
  font-size: 0.875rem;
}

.sensor-card strong {
  font-size: 2rem;
  font-weight: 700;
}

.sensor-card strong small {
  margin-left: 6px;
  font-size: 1rem;
}

.sensor-card p {
  margin: 0;
  color: #718096;
}

.sensor-card--warning {
  background: #fff7e8;
}

.sensor-card--critical {
  background: #fff0f0;
  animation: room-alert-pulse 1.6s ease-in-out infinite;
}

.history-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 16px;
}

.history-grid h2 {
  margin: 0 0 14px;
  font-size: 1.25rem;
  font-weight: 600;
}

.history-chart {
  display: grid;
  grid-template-columns: repeat(12, minmax(0, 1fr));
  gap: 8px;
  align-items: end;
  min-height: 160px;
}

.history-chart__bar {
  border-radius: 999px 999px 6px 6px;
  background: linear-gradient(180deg, #2d9e5f, #1b6b3a);
}

@keyframes room-alert-pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(197, 48, 48, 0.15); }
  50% { box-shadow: 0 0 0 10px rgba(197, 48, 48, 0); }
}

@media (max-width: 767px) {
  .sensor-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}
</style>
