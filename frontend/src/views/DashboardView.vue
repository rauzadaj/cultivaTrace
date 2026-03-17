<template>
  <div class="dashboard-view">
    <header class="dashboard-view__header">
      <div>
        <p class="dashboard-view__eyebrow">Overview</p>
        <h1>Bonjour {{ firstName }}</h1>
        <p class="dashboard-view__date">{{ todayLabel }}</p>
      </div>
    </header>

    <div v-if="cropStore.loading" class="dashboard-skeleton">
      <q-skeleton v-for="index in 6" :key="index" height="132px" class="dashboard-skeleton__item" />
    </div>

    <template v-else>
      <div v-if="isMobile" class="dashboard-mobile">
        <div class="stats-scroll">
          <div v-for="chip in statChips" :key="chip.id" class="stats-scroll__item" :class="`stats-scroll__item--${chip.tone}`">
            <strong>{{ chip.value }}</strong>
            <span>{{ chip.label }}</span>
          </div>
        </div>

        <c-card>
          <div class="section-head">
            <div>
              <p class="section-head__eyebrow">Alertes actives</p>
              <h2>Actions terrain prioritaires</h2>
            </div>
          </div>

          <div v-if="alerts.length" class="alert-stack">
            <article v-for="alert in alerts" :key="alert.id" class="alert-card" :class="`alert-card--${alert.severity}`">
              <alert-badge :severity="alert.severity">{{ alert.title }}</alert-badge>
              <p>{{ alert.message }}</p>
              <small>{{ alert.context }}</small>
            </article>
          </div>

          <div v-else class="empty-state empty-state--positive">
            <q-icon name="mdi-check-circle-outline" size="28px" />
            <strong>Aucune alerte active</strong>
            <span>Les salles et les plants suivis sont dans une zone stable.</span>
          </div>
        </c-card>

        <section>
          <div class="section-head">
            <div>
              <p class="section-head__eyebrow">Plants a surveiller</p>
              <h2>Priorites du shift</h2>
            </div>
          </div>

          <div class="plant-carousel">
            <c-card v-for="plant in spotlightPlants" :key="plant.id" class="plant-card">
              <div class="plant-card__head">
                <plant-stage-chip :stage="plant.stage" />
                <span>{{ plant.ageInDays }} j</span>
              </div>
              <strong>{{ plant.name }}</strong>
              <p>{{ plant.strain }}</p>
              <small>{{ plant.room }}</small>
            </c-card>
          </div>
        </section>
      </div>

      <div v-else class="dashboard-desktop">
        <section class="desktop-grid">
          <c-card>
            <div class="section-head">
              <div>
                <p class="section-head__eyebrow">KPIs</p>
                <h2>Shift overview</h2>
              </div>
            </div>
            <div class="kpi-grid">
              <div v-for="chip in statChips" :key="chip.id" class="kpi-item">
                <strong>{{ chip.value }}</strong>
                <span>{{ chip.label }}</span>
              </div>
            </div>
          </c-card>

          <c-card>
            <div class="section-head">
              <div>
                <p class="section-head__eyebrow">Plants par stade</p>
                <h2>Distribution active</h2>
              </div>
            </div>
            <div class="stage-chart">
              <div v-for="bar in stageBars" :key="bar.id" class="stage-chart__row">
                <div class="stage-chart__label">
                  <plant-stage-chip :stage="bar.stage" />
                  <span>{{ bar.count }}</span>
                </div>
                <div class="stage-chart__track">
                  <div class="stage-chart__fill" :style="{ width: `${bar.width}%` }" />
                </div>
              </div>
            </div>
          </c-card>

          <c-card>
            <div class="section-head">
              <div>
                <p class="section-head__eyebrow">Alertes</p>
                <h2>Capteurs et terrain</h2>
              </div>
            </div>
            <div v-if="alerts.length" class="alert-stack">
              <article v-for="alert in alerts.slice(0, 4)" :key="alert.id" class="alert-card" :class="`alert-card--${alert.severity}`">
                <alert-badge :severity="alert.severity">{{ alert.title }}</alert-badge>
                <p>{{ alert.message }}</p>
              </article>
            </div>
            <div v-else class="empty-state empty-state--positive">
              <q-icon name="mdi-check-circle-outline" size="28px" />
              <strong>Aucune alerte active</strong>
            </div>
          </c-card>
        </section>

        <c-card class="q-mt-md">
          <div class="section-head">
            <div>
              <p class="section-head__eyebrow">Dernieres actions</p>
              <h2>Journal recent</h2>
            </div>
          </div>
          <div class="action-table">
            <header class="action-table__head">
              <span>Type</span>
              <span>Contexte</span>
              <span>Date</span>
            </header>
            <article v-for="entry in cropStore.latestEntries.slice(0, 6)" :key="entry.id" class="action-table__row">
              <span>{{ entry.type }}</span>
              <span>{{ entry.notes || 'Observation terrain' }}</span>
              <span>{{ formatDateTime(entry.occurredAt) }}</span>
            </article>
          </div>
        </c-card>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import AlertBadge from '../components/ui/AlertBadge.vue'
import CCard from '../components/ui/CCard.vue'
import PlantStageChip from '../components/ui/PlantStageChip.vue'
import { useDisplay } from '../composables/useDisplay'
import { useCropStore } from '../stores/useCropStore'
import { useUserStore } from '../stores/useUserStore'
import type { AlertItem, DashboardStatChip, PlantCardSummary } from '../types/api'

const cropStore = useCropStore()
const userStore = useUserStore()
const { xs } = useDisplay()

const isMobile = computed(() => xs.value)
const firstName = computed(() => (userStore.operatorLabel || userStore.userEmail || 'operateur').split(/[\s@._-]+/)[0] ?? 'operateur')
const todayLabel = computed(() => new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }))

const statChips = computed<DashboardStatChip[]>(() => [
  { id: 'active', label: 'Plants actifs', value: String(cropStore.activeCrops.length), tone: 'positive' },
  { id: 'alerts', label: 'Alertes', value: String(alerts.value.length), tone: alerts.value.length ? 'warning' : 'default' },
  { id: 'harvests', label: 'Prochaines recoltes', value: String(cropStore.harvestedCrops.length), tone: 'default' },
])

const alerts = computed<AlertItem[]>(() => {
  const items: AlertItem[] = []

  if (cropStore.error) {
    items.push({
      id: 'dashboard-error',
      title: 'Erreur de synchronisation',
      message: cropStore.error,
      severity: 'critical',
      context: 'Verifiez la connectivite et le backend',
    })
  }

  cropStore.services
    .filter((service) => service.tone !== 'success')
    .slice(0, 2)
    .forEach((service) => {
      items.push({
        id: service.id,
        title: service.name,
        message: service.statusLabel,
        severity: service.tone === 'warning' ? 'warning' : 'critical',
        context: service.description,
      })
    })

  return items
})

const spotlightPlants = computed<PlantCardSummary[]>(() => cropStore.activeCrops.slice(0, 3).map((crop) => ({
  id: crop.id,
  name: crop.displayName,
  strain: crop.genetic.name,
  room: crop.batchCode,
  stage: crop.currentStage,
  ageInDays: Math.max(1, Math.round((Date.now() - new Date(crop.seededAt).getTime()) / 86_400_000)),
  batchCode: crop.batchCode,
})))

const stageBars = computed(() => {
  const counts = [
    { id: 'seedling', stage: 'seedling', count: cropStore.crops.filter((crop) => crop.currentStage === 'seedling').length },
    { id: 'veg', stage: 'veg', count: cropStore.crops.filter((crop) => crop.currentStage === 'veg').length },
    { id: 'flower', stage: 'flower', count: cropStore.crops.filter((crop) => crop.currentStage === 'flower').length },
    { id: 'harvest', stage: 'harvest', count: cropStore.crops.filter((crop) => crop.currentStage === 'harvest').length },
  ] as const
  const maxCount = Math.max(...counts.map((item) => item.count), 1)

  return counts.map((item) => ({
    ...item,
    width: Math.max((item.count / maxCount) * 100, item.count ? 14 : 0),
  }))
})

function formatDateTime(value: string) {
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<style scoped>
.dashboard-view {
  display: grid;
  gap: 16px;
}

.dashboard-view__header h1 {
  margin: 0;
  font-size: clamp(1.8rem, 3vw, 2.4rem);
  font-weight: 600;
}

.dashboard-view__eyebrow,
.section-head__eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.dashboard-view__date {
  margin: 6px 0 0;
  color: #718096;
}

.dashboard-skeleton {
  display: grid;
  gap: 16px;
}

.dashboard-skeleton__item {
  border-radius: 12px;
}

.stats-scroll {
  display: flex;
  gap: 12px;
  overflow-x: auto;
  padding-bottom: 4px;
}

.stats-scroll__item {
  flex: 0 0 168px;
  display: grid;
  gap: 4px;
  padding: 14px 16px;
  border-radius: 16px;
  background: #fff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.stats-scroll__item strong {
  font-size: 1.5rem;
}

.stats-scroll__item--warning {
  background: #fff5e7;
}

.section-head {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 14px;
}

.section-head h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
}

.alert-stack {
  display: grid;
  gap: 12px;
}

.alert-card {
  display: grid;
  gap: 8px;
  padding: 14px;
  border-radius: 12px;
}

.alert-card p,
.alert-card small {
  margin: 0;
}

.alert-card--warning {
  background: #fff9ec;
}

.alert-card--critical {
  background: #fff1f1;
}

.empty-state {
  display: grid;
  justify-items: start;
  gap: 8px;
  padding: 16px;
  border-radius: 12px;
  background: #f8fafc;
  color: #4a5568;
}

.empty-state--positive {
  background: #e8f5ee;
  color: #1b6b3a;
}

.plant-carousel {
  display: flex;
  gap: 12px;
  overflow-x: auto;
}

.plant-card {
  flex: 0 0 min(280px, 84vw);
  display: grid;
  gap: 10px;
}

.plant-card__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
}

.plant-card strong,
.plant-card p,
.plant-card small {
  margin: 0;
}

.desktop-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 16px;
}

.kpi-grid {
  display: grid;
  gap: 12px;
}

.kpi-item {
  padding: 14px;
  border-radius: 12px;
  background: #f8fafc;
}

.kpi-item strong {
  display: block;
  font-size: 1.75rem;
}

.stage-chart {
  display: grid;
  gap: 12px;
}

.stage-chart__row {
  display: grid;
  gap: 10px;
}

.stage-chart__label {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.stage-chart__track {
  height: 12px;
  border-radius: 999px;
  background: #edf2f7;
  overflow: hidden;
}

.stage-chart__fill {
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, #1b6b3a, #2d9e5f);
}

.action-table {
  display: grid;
  gap: 10px;
}

.action-table__head,
.action-table__row {
  display: grid;
  grid-template-columns: 160px minmax(0, 1fr) 140px;
  gap: 12px;
}

.action-table__head {
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.action-table__row {
  padding: 12px 0;
  border-top: 1px solid #edf2f7;
}

@media (max-width: 1024px) {
  .desktop-grid {
    grid-template-columns: 1fr;
  }
}
</style>
