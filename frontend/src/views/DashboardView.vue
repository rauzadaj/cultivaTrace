<template>
  <div class="dashboard-view">
    <header class="dashboard-view__header">
      <div>
        <p class="dashboard-view__eyebrow">Overview</p>
        <h1>Bonjour {{ firstName }}</h1>
        <p class="dashboard-view__date">{{ todayLabel }}</p>
      </div>
    </header>

    <div v-if="plantsStore.loading" class="dashboard-skeleton">
      <q-skeleton v-for="index in 6" :key="index" height="132px" class="dashboard-skeleton__item" />
    </div>

    <template v-else>
      <q-pull-to-refresh v-if="isMobile" @refresh="refreshDashboard">
        <div class="dashboard-mobile">
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
                <small v-if="alert.context">{{ alert.context }}</small>
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
      </q-pull-to-refresh>

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
            <article v-for="entry in plantsStore.recentEvents.slice(0, 6)" :key="entry.id" class="action-table__row">
              <span>{{ entry.eventType }}</span>
              <span>{{ entry.notes || eventContext(entry) }}</span>
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
import type { PlantEvent } from '@/types/api'
import AlertBadge from '../components/ui/AlertBadge.vue'
import CCard from '../components/ui/CCard.vue'
import PlantStageChip from '../components/ui/PlantStageChip.vue'
import { useDisplay } from '../composables/useDisplay'
import { usePlantsStore } from '../stores/plants'
import { useUserStore } from '../stores/useUserStore'
import type { AlertItem, DashboardStatChip, PlantCardSummary, PlantStage } from '../types/api'

const plantsStore = usePlantsStore()
const userStore = useUserStore()
const { xs } = useDisplay()

const isMobile = computed(() => xs.value)
const firstName = computed(() => (userStore.operatorLabel || userStore.userEmail || 'operateur').split(/[\s@._-]+/)[0] ?? 'operateur')
const todayLabel = computed(() => new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }))

const roomOccupancy = computed(() => plantsStore.rooms.map((room) => ({
  room,
  count: plantsStore.plants.filter((plant) => {
    if (typeof plant.room === 'string') {
      return plant.room.endsWith(`/${room.id}`)
    }

    return plant.room.id === room.id
  }).length,
})))

const alerts = computed<AlertItem[]>(() => {
  const items: AlertItem[] = []

  if (plantsStore.error) {
    items.push({
      id: 'dashboard-error',
      title: 'Erreur de synchronisation',
      message: plantsStore.error,
      severity: 'critical',
      context: 'Verifiez la connectivite et le backend',
    })
  }

  roomOccupancy.value
    .filter(({ room, count }) => count >= room.capacityMax)
    .forEach(({ room, count }) => {
      items.push({
        id: `room-capacity-${room.id}`,
        title: room.name,
        message: `${count}/${room.capacityMax} plants actifs`,
        severity: 'warning',
        context: 'Capacite maximale atteinte',
      })
    })

  return items
})

const statChips = computed<DashboardStatChip[]>(() => [
  { id: 'active', label: 'Plants actifs', value: String(plantsStore.activePlants.length), tone: 'positive' },
  { id: 'rooms', label: 'Salles', value: String(plantsStore.rooms.length), tone: 'default' },
  { id: 'alerts', label: 'Alertes', value: String(alerts.value.length), tone: alerts.value.length ? 'warning' : 'default' },
])

const spotlightPlants = computed<PlantCardSummary[]>(() => plantsStore.activePlants.slice(0, 3))

const stageBars = computed(() => {
  const stages: Array<{ id: PlantStage; stage: PlantStage }> = [
    { id: 'germination', stage: 'germination' },
    { id: 'vegetation', stage: 'vegetation' },
    { id: 'flowering', stage: 'flowering' },
    { id: 'harvest', stage: 'harvest' },
  ]
  const counts = stages.map((item) => ({
    ...item,
    count: plantsStore.plants.filter((plant) => plant.stage === item.stage).length,
  }))
  const maxCount = Math.max(...counts.map((item) => item.count), 1)

  return counts.map((item) => ({
    ...item,
    width: Math.max((item.count / maxCount) * 100, item.count ? 14 : 0),
  }))
})

function eventContext(entry: PlantEvent) {
  if (entry.eventType === 'stage_change' && entry.payload && 'to' in entry.payload) {
    return `Passage au stade ${String(entry.payload.to)}`
  }

  if (entry.eventType === 'germination') {
    return 'Mise en culture du plant'
  }

  return 'Evenement terrain'
}

function formatDateTime(value: string) {
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function refreshDashboard(done: () => void) {
  await plantsStore.bootstrap()
  done()
}
</script>

<style scoped lang="scss">
@use '../css/breakpoints.sass' as bp;

.dashboard-view { display: grid; gap: 16px; }
.dashboard-mobile { display: grid; gap: 16px; padding-bottom: 120px; min-width: 0; }
.dashboard-view__header h1 { margin: 0; font-size: 1.75rem; font-weight: 600; line-height: 1.1; }
.dashboard-view__eyebrow, .section-head__eyebrow { margin: 0 0 6px; color: #718096; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.12em; }
.dashboard-view__date { margin: 6px 0 0; color: #718096; }
.dashboard-skeleton { display: grid; gap: 16px; }
.dashboard-skeleton__item { border-radius: 16px; }
.stats-scroll { display: grid; grid-template-columns: 1fr; gap: 12px; width: 100%; padding: 0; }
.stats-scroll__item { display: grid; gap: 4px; min-width: 0; padding: 14px 16px; border-radius: 16px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
.stats-scroll__item strong { font-size: 1.5rem; }
.stats-scroll__item--warning { background: #fff5e7; }
.section-head { display: flex; justify-content: space-between; gap: 12px; align-items: start; margin-bottom: 16px; }
.section-head h2 { margin: 0; font-size: 1.25rem; font-weight: 600; }
.alert-stack { display: grid; gap: 12px; }
.alert-card { display: grid; gap: 10px; padding: 16px; border-radius: 16px; }
.alert-card p, .alert-card small { margin: 0; }
.alert-card--warning { background: #fff6df; }
.alert-card--critical { background: #fdecec; }
.empty-state { display: grid; justify-items: start; gap: 10px; padding: 8px 0; }
.empty-state--positive { color: #1b6b3a; }
.plant-carousel { display: grid; grid-template-columns: 1fr; gap: 12px; }
.plant-card { display: grid; gap: 10px; }
.plant-card strong, .plant-card p, .plant-card small { margin: 0; }
.plant-card p, .plant-card small { color: #718096; }
.plant-card__head { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.dashboard-desktop { display: grid; gap: 16px; }
.desktop-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
.kpi-grid { display: grid; gap: 12px; }
.kpi-item { display: grid; gap: 4px; padding: 16px; border-radius: 14px; background: #f8fafc; }
.kpi-item strong { font-size: 1.5rem; }
.stage-chart { display: grid; gap: 12px; }
.stage-chart__row { display: grid; gap: 8px; }
.stage-chart__label { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.stage-chart__track { overflow: hidden; height: 10px; border-radius: 999px; background: #e8edf3; }
.stage-chart__fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #2d9e5f, #1b6b3a); }
.action-table { display: grid; gap: 10px; }
.action-table__head, .action-table__row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
.action-table__head { color: #718096; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.08em; }
.action-table__row { padding-top: 10px; border-top: 1px solid #e2e8f0; }
@include bp.mobile-wide { .stats-scroll, .plant-carousel { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@include bp.mobile { .dashboard-view__header h1 { font-size: 1.75rem; } }
@include bp.desktop { .dashboard-view__header h1 { font-size: 2.5rem; } }
</style>
