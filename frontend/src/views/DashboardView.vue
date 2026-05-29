<template>
  <div class="dashboard-view">
    <header class="dashboard-view__header">
      <div>
        <p class="dashboard-view__eyebrow">Overview</p>
        <h1>Hello {{ firstName }}</h1>
        <p class="dashboard-view__date">{{ todayLabel }}</p>
      </div>

      <q-btn
        color="primary"
        icon="mdi-home-plus-outline"
        label="New farm"
        class="dashboard-view__farm-btn"
        no-caps
        @click="farmDialogOpen = true"
      />
    </header>

    <div v-if="loading" class="dashboard-skeleton">
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
                <p class="section-head__eyebrow">Active alerts</p>
                <h2>Priority field actions</h2>
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
              <strong>No active alerts</strong>
              <span>All monitored rooms and plants are in a stable zone.</span>
            </div>
          </c-card>

          <section>
            <div class="section-head">
              <div>
                <p class="section-head__eyebrow">Plants to watch</p>
                <h2>Shift priorities</h2>
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
                <p class="section-head__eyebrow">Plants by stage</p>
                <h2>Active distribution</h2>
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
                <p class="section-head__eyebrow">Alerts</p>
                <h2>Sensors and field</h2>
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
              <strong>No active alerts</strong>
            </div>
          </c-card>
        </section>

        <c-card class="q-mt-md">
          <div class="section-head">
            <div>
              <p class="section-head__eyebrow">Recent actions</p>
              <h2>Activity log</h2>
            </div>
          </div>
          <div class="action-table">
            <header class="action-table__head">
              <span>Type</span>
              <span>Context</span>
              <span>Date</span>
            </header>
            <article v-for="entry in recentEvents" :key="entry.id" class="action-table__row">
              <span>{{ entry.eventType }}</span>
              <span>{{ entry.notes || eventContext(entry) }}</span>
              <span>{{ formatDateTime(entry.occurredAt) }}</span>
            </article>
          </div>
        </c-card>
      </div>
    </template>
    <farm-form v-model="farmDialogOpen" @created="handleFarmCreated" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { dashboardApi } from '@/services/api'
import type {
  DashboardAlert,
  DashboardOverviewResponse,
  DashboardRecentEvent,
  DashboardSpotlightPlant,
  PlantStage,
} from '@/types/api'
import FarmForm from '@/components/farms/FarmForm.vue'
import AlertBadge from '../components/ui/AlertBadge.vue'
import CCard from '../components/ui/CCard.vue'
import PlantStageChip from '../components/ui/PlantStageChip.vue'
import { useDisplay } from '../composables/useDisplay'
import { useAuthStore } from '../stores/auth'
import type { DashboardStatChip } from '../types/api'

const authStore = useAuthStore()
const { xs } = useDisplay()

const farmDialogOpen = ref(false)
const loading = ref(true)
const loadError = ref<string | null>(null)
const overview = ref<DashboardOverviewResponse | null>(null)
const isMobile = computed(() => xs.value)
const firstName = computed(() => {
  const identity = authStore.user?.email || 'operator'

  return identity.split(/[\s@._-]+/)[0] ?? 'operator'
})
const todayLabel = computed(() => new Date().toLocaleDateString('en-US', { weekday: 'long', day: 'numeric', month: 'long' }))

const alerts = computed<DashboardAlert[]>(() => {
  const items: DashboardAlert[] = []

  if (loadError.value) {
    items.push({
      id: 'dashboard-error',
      title: 'Sync error',
      message: loadError.value,
      severity: 'critical',
      context: 'Check connectivity and backend',
    })
  }

  if (overview.value?.alerts.items.length) {
    items.push(...overview.value.alerts.items)
  }

  return items
})

const statChips = computed<DashboardStatChip[]>(() => [
  { id: 'active', label: 'Active plants', value: String(overview.value?.plants.total ?? 0), tone: 'positive' },
  { id: 'rooms', label: 'Rooms', value: String(overview.value?.rooms.total ?? 0), tone: 'default' },
  { id: 'alerts', label: 'Alerts', value: String(alerts.value.length), tone: alerts.value.length ? 'warning' : 'default' },
])

const spotlightPlants = computed<DashboardSpotlightPlant[]>(() => overview.value?.overview.spotlightPlants ?? [])
const recentEvents = computed<DashboardRecentEvent[]>(() => overview.value?.overview.recentEvents ?? [])

const stageBars = computed(() => {
  const stages: Array<{ id: PlantStage; stage: PlantStage }> = [
    { id: 'germination', stage: 'germination' },
    { id: 'vegetation', stage: 'vegetation' },
    { id: 'flowering', stage: 'flowering' },
    { id: 'harvest', stage: 'harvest' },
  ]
  const counts = stages.map((item) => ({
    ...item,
    count: overview.value?.plants.byStage[item.stage] ?? 0,
  }))
  const maxCount = Math.max(...counts.map((item) => item.count), 1)

  return counts.map((item) => ({
    ...item,
    width: Math.max((item.count / maxCount) * 100, item.count ? 14 : 0),
  }))
})

function eventContext(entry: DashboardRecentEvent) {
  if (entry.eventType === 'stage_change' && entry.payload && 'to' in entry.payload) {
    return `Stage change to ${String(entry.payload.to)}`
  }

  if (entry.eventType === 'germination') {
    return 'Plant started'
  }

  return 'Field event'
}

function formatDateTime(value: string) {
  return new Date(value).toLocaleString('en-US', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function fetchOverview(): Promise<void> {
  loading.value = true
  loadError.value = null

  try {
    const { data } = await dashboardApi.overview()
    overview.value = data
  } catch (error) {
    loadError.value = error instanceof Error ? error.message : 'Failed to load dashboard.'
    throw error
  } finally {
    loading.value = false
  }
}

onMounted(async () => {
  try {
    await fetchOverview()
  } catch (error) {
    console.error('Dashboard overview failed', error)
  }
})

async function handleFarmCreated() {
  await fetchOverview()
}

async function refreshDashboard(done: () => void) {
  try {
    await fetchOverview()
  } finally {
    done()
  }
}
</script>

<style scoped lang="scss">
@use '../css/breakpoints.sass' as bp;

.dashboard-view { display: grid; gap: 20px; }
.dashboard-view__header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; }
.dashboard-view__farm-btn { min-height: 44px; border-radius: 8px; }

.dashboard-mobile { display: grid; gap: 16px; padding-bottom: 120px; min-width: 0; }
.dashboard-view__header h1 { margin: 0; font-size: 1.875rem; font-weight: 700; line-height: 1.1; letter-spacing: -0.035em; color: var(--ct-text-1); }
.dashboard-view__eyebrow, .section-head__eyebrow { margin: 0 0 4px; color: var(--ct-accent); font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.12em; font-weight: 700; }
.dashboard-view__date { margin: 6px 0 0; color: var(--ct-text-2); font-size: 0.875rem; }
.dashboard-skeleton { display: grid; gap: 14px; }
.dashboard-skeleton__item { border-radius: 14px; background: #FFFFFF; }
.stats-scroll { display: grid; grid-template-columns: 1fr; gap: 12px; }
.stats-scroll__item { display: grid; gap: 6px; padding: 18px; border-radius: 14px; background: #FFFFFF; border: 1px solid var(--ct-border); box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
.stats-scroll__item strong { font-size: 2.25rem; font-weight: 700; letter-spacing: -0.04em; color: var(--ct-text-1); line-height: 1; }
.stats-scroll__item span { font-size: 0.75rem; color: var(--ct-text-2); font-weight: 500; }
.stats-scroll__item--warning { background: var(--ct-warning-light); border-color: #FDE68A; }
.stats-scroll__item--warning strong { color: #92400E; }
.section-head { display: flex; justify-content: space-between; gap: 12px; align-items: start; margin-bottom: 16px; }
.section-head h2 { margin: 0; font-size: 1.0625rem; font-weight: 600; letter-spacing: -0.025em; color: var(--ct-text-1); }
.alert-stack { display: grid; gap: 10px; }
.alert-card { display: grid; gap: 8px; padding: 14px; border-radius: 12px; border: 1px solid; }
.alert-card p, .alert-card small { margin: 0; }
.alert-card p { color: var(--ct-text-2); font-size: 0.875rem; line-height: 1.5; }
.alert-card small { color: var(--ct-text-3); font-size: 0.8rem; }
.alert-card--warning { background: var(--ct-warning-light); border-color: #FDE68A; }
.alert-card--critical { background: var(--ct-danger-light); border-color: #FECACA; }
.empty-state { display: grid; justify-items: start; gap: 8px; padding: 8px 0; }
.empty-state--positive { color: var(--ct-accent); }
.empty-state strong { color: var(--ct-text-1); font-size: 0.9375rem; }
.empty-state span { color: var(--ct-text-2); font-size: 0.875rem; }
.plant-carousel { display: grid; grid-template-columns: 1fr; gap: 12px; }
.plant-card { display: grid; gap: 10px; }
.plant-card strong, .plant-card p, .plant-card small { margin: 0; }
.plant-card strong { font-size: 0.9375rem; letter-spacing: -0.015em; color: var(--ct-text-1); font-weight: 600; }
.plant-card p, .plant-card small { color: var(--ct-text-2); font-size: 0.8125rem; }
.plant-card__head { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.plant-card__head span { color: var(--ct-text-3); font-size: 0.8125rem; }
.dashboard-desktop { display: grid; gap: 16px; }
.desktop-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 16px; }
.kpi-grid { display: grid; gap: 12px; }
.kpi-item { display: grid; gap: 6px; padding: 20px; border-radius: 12px; background: var(--ct-surface-2); border: 1px solid var(--ct-border); }
.kpi-item strong { font-size: 2.5rem; font-weight: 700; letter-spacing: -0.045em; color: var(--ct-text-1); line-height: 1; }
.kpi-item span { font-size: 0.7rem; color: var(--ct-text-2); text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; }
.stage-chart { display: grid; gap: 16px; }
.stage-chart__row { display: grid; gap: 8px; }
.stage-chart__label { display: flex; justify-content: space-between; gap: 12px; align-items: center; }
.stage-chart__label span { color: var(--ct-text-3); font-size: 0.8125rem; font-weight: 500; }
.stage-chart__track { overflow: hidden; height: 6px; border-radius: 999px; background: var(--ct-surface-3); }
.stage-chart__fill { height: 100%; border-radius: inherit; background: linear-gradient(90deg, #15803D, #22C55E); }
.action-table { display: grid; gap: 0; }
.action-table__head, .action-table__row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; padding: 10px 0; }
.action-table__head { color: var(--ct-text-3); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.1em; font-weight: 600; border-bottom: 1px solid var(--ct-border); }
.action-table__row { border-bottom: 1px solid var(--ct-border); font-size: 0.875rem; color: var(--ct-text-1); }
.action-table__row:last-child { border-bottom: none; }
@include bp.mobile-wide { .stats-scroll, .plant-carousel { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@include bp.mobile { .dashboard-view__header h1 { font-size: 1.75rem; } }
@include bp.desktop { .dashboard-view__header h1 { font-size: 2.25rem; } }
</style>
