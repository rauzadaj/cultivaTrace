<template>
  <v-app class="material-shell">
    <v-navigation-drawer
      v-model="drawer"
      :permanent="mdAndUp"
      :temporary="!mdAndUp"
      width="272"
      class="dashboard-drawer"
    >
      <div class="drawer-brand">
        <div class="drawer-brand__mark">CT</div>
        <div>
          <strong>CultivaTrace</strong>
          <span>Field operations suite</span>
        </div>
      </div>

      <v-list nav density="compact" class="nav-list">
        <v-list-item
          v-for="item in navigationItems"
          :key="item.title"
          :prepend-icon="item.icon"
          :title="item.title"
          :subtitle="item.subtitle"
          active
          active-color="white"
          rounded="lg"
        />
      </v-list>

      <div class="drawer-panel">
        <p class="drawer-panel__title">Lifecycle</p>
        <div v-for="node in stageNodes" :key="node.key" class="filter-row">
          <div class="filter-row__label">
            <span class="filter-dot" :class="`filter-dot--${node.key}`" />
            <span>{{ node.label }}</span>
          </div>
          <strong>{{ node.count }}</strong>
        </div>
      </div>

      <div class="drawer-panel">
        <p class="drawer-panel__title">Signals</p>
        <div class="filter-row">
          <span>Journal entries</span>
          <strong>{{ cropStore.journalEntries.length }}</strong>
        </div>
        <div class="filter-row">
          <span>Average pH</span>
          <strong>{{ averagePhLabel }}</strong>
        </div>
        <div class="filter-row">
          <span>Avg nutrients</span>
          <strong>{{ averagePpmLabel }}</strong>
        </div>
      </div>

      <div class="drawer-panel">
        <p class="drawer-panel__title">Workspace</p>
        <div class="workspace-summary">
          <span>{{ userStore.userEmail || userStore.operatorLabel }}</span>
          <small>{{ syncLabel }}</small>
        </div>
      </div>
    </v-navigation-drawer>

    <v-app-bar flat color="#2b3237" density="comfortable" class="dashboard-appbar">
      <v-app-bar-nav-icon v-if="!mdAndUp" color="white" @click="drawer = !drawer" />

      <div class="appbar-search">
        <v-icon icon="mdi-magnify" size="18" />
        <input
          v-model.trim="search"
          type="text"
          placeholder="Find lot, batch or genetic"
          aria-label="Search lots"
        >
      </div>

      <v-spacer />
      <v-chip size="small" variant="flat" color="primary" class="mr-2">Overview</v-chip>
      <v-chip size="small" variant="outlined" color="white" class="mr-2">{{ userStore.operatorLabel }}</v-chip>
      <v-btn size="small" variant="text" color="white" @click="logout">Logout</v-btn>
    </v-app-bar>

    <v-main class="dashboard-main">
      <v-container fluid class="pa-4">
        <v-row dense>
          <v-col cols="12" lg="8">
            <v-card flat class="surface-card updates-card">
              <div class="section-header">
                <div>
                  <p class="section-header__eyebrow">Updates</p>
                  <h1>Lot operations</h1>
                </div>
                <v-btn color="primary" variant="flat" rounded="lg" :loading="cropStore.loading" @click="cropStore.loadDashboard">
                  Refresh
                </v-btn>
              </div>

              <div class="summary-strip">
                <div v-for="item in summaryItems" :key="item.label" class="summary-strip__item">
                  <strong>{{ item.value }}</strong>
                  <span>{{ item.label }}</span>
                </div>
              </div>

              <div class="updates-table">
                <header class="updates-table__head">
                  <span>Lot</span>
                  <span>Stage</span>
                  <span>Batch</span>
                  <span>Seeded</span>
                  <span>Action</span>
                </header>

                <div v-if="!visibleCrops.length" class="empty-state">Aucun lot disponible pour ce filtre.</div>

                <article v-for="crop in visibleCrops" :key="crop.id" class="updates-row">
                  <div class="updates-row__lot">
                    <span class="lot-indicator" :class="`lot-indicator--${crop.currentStage}`" />
                    <div>
                      <strong>{{ crop.displayName }}</strong>
                      <p>{{ crop.genetic.code }} · {{ crop.genetic.name }}</p>
                    </div>
                  </div>

                  <div class="updates-row__stage">
                    <span class="lot-chip" :class="`lot-chip--${crop.currentStage}`">{{ stageLabel(crop.currentStage) }}</span>
                  </div>

                  <div class="updates-row__batch">
                    <strong>{{ crop.batchCode }}</strong>
                    <small>{{ crop.currentStage === 'harvest' ? 'closed cycle' : 'active cycle' }}</small>
                  </div>

                  <div class="updates-row__seeded">
                    <strong>{{ formatDate(crop.seededAt) }}</strong>
                    <small>{{ crop.harvestedAt ? `Harvest ${formatDate(crop.harvestedAt)}` : 'In progress' }}</small>
                  </div>

                  <div class="updates-row__action">
                    <template v-if="crop.currentStage !== 'harvest'">
                      <QuickActionButtons :crop-iri="crop['@id']" :disabled="cropStore.loading" />
                    </template>
                    <template v-else>
                      <div class="harvest-pill">
                        <v-icon icon="mdi-check-decagram" size="18" />
                        <span>{{ crop.finalYieldGrams ?? '—' }} g</span>
                      </div>
                    </template>
                  </div>
                </article>
              </div>
            </v-card>
          </v-col>

          <v-col cols="12" lg="4">
            <v-card flat class="surface-card session-card mb-4">
              <div class="section-header section-header--compact">
                <div>
                  <p class="section-header__eyebrow">Console</p>
                  <h2>Workspace access</h2>
                </div>
              </div>

              <form class="session-form" @submit.prevent>
                <v-text-field
                  :model-value="userStore.apiBaseUrl"
                  label="API base URL"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  @update:model-value="userStore.setApiBaseUrl(String($event))"
                />
                <v-text-field
                  :model-value="userStore.operatorLabel"
                  label="Operateur"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  @update:model-value="userStore.setOperatorLabel(String($event))"
                />
                <v-text-field
                  :model-value="userStore.userEmail"
                  label="Compte"
                  variant="outlined"
                  density="comfortable"
                  hide-details
                  readonly
                />
              </form>
            </v-card>

            <v-card flat class="surface-card services-card mb-4">
              <div class="section-header section-header--compact">
                <div>
                  <p class="section-header__eyebrow">Services</p>
                  <h2>System health</h2>
                </div>
              </div>

              <div class="service-list">
                <article v-for="service in serviceItems" :key="service.title" class="service-item">
                  <div class="service-item__icon" :class="`service-item__icon--${service.tone}`">
                    <v-icon :icon="service.icon" size="20" />
                  </div>
                  <div>
                    <strong>{{ service.title }}</strong>
                    <p>{{ service.description }}</p>
                  </div>
                </article>
              </div>
            </v-card>

            <v-card flat class="surface-card comments-card">
              <div class="section-header section-header--compact">
                <div>
                  <p class="section-header__eyebrow">Comments</p>
                  <h2>Field notes</h2>
                </div>
              </div>

              <div v-if="!cropStore.latestEntries.length" class="empty-state empty-state--compact">
                Aucun signal recent.
              </div>
              <div v-else class="stream-list">
                <article v-for="entry in cropStore.latestEntries" :key="entry.id" class="stream-item">
                  <div class="stream-item__icon">
                    <v-icon :icon="entryIcon(entry.type)" size="18" />
                  </div>
                  <div>
                    <strong>{{ entryLabel(entry.type) }}</strong>
                    <p>{{ entry.crop.displayName }}</p>
                    <small>{{ entry.notes ?? 'Signal capture sans note.' }}</small>
                  </div>
                </article>
              </div>
            </v-card>
          </v-col>
        </v-row>

        <v-row dense class="mt-1">
          <v-col cols="12" lg="8">
            <v-card flat class="surface-card analytics-card">
              <div class="section-header section-header--compact">
                <div>
                  <p class="section-header__eyebrow">Analytics</p>
                  <h2>Genetic performance</h2>
                </div>
              </div>

              <div v-if="!cropStore.cycleAverages.length" class="empty-state empty-state--compact">
                Aucune moyenne calculee.
              </div>
              <div v-else class="performance-list">
                <article v-for="item in cropStore.cycleAverages" :key="item.geneticId" class="performance-row">
                  <div>
                    <strong>{{ item.geneticCode }}</strong>
                    <p>{{ item.geneticName }}</p>
                  </div>
                  <div class="performance-row__bar">
                    <div class="performance-row__track">
                      <div class="performance-row__fill" :style="{ width: `${analyticsWidth(item.averageCycleDays)}%` }" />
                    </div>
                    <small>{{ item.completedCycles }} cycles · {{ item.averageCycleDays.toFixed(1) }} jours</small>
                  </div>
                </article>
              </div>
            </v-card>
          </v-col>

          <v-col cols="12" lg="4">
            <v-card flat class="surface-card telemetry-card">
              <div class="section-header section-header--compact">
                <div>
                  <p class="section-header__eyebrow">Telemetry</p>
                  <h2>Realtime metrics</h2>
                </div>
              </div>

              <div class="telemetry-grid">
                <div class="telemetry-card__item">
                  <strong>{{ cropStore.journalEntries.length }}</strong>
                  <span>events</span>
                </div>
                <div class="telemetry-card__item">
                  <strong>{{ cropStore.averageYield ?? '—' }}</strong>
                  <span>yield g</span>
                </div>
                <div class="telemetry-card__item">
                  <strong>{{ averagePhLabel }}</strong>
                  <span>average pH</span>
                </div>
                <div class="telemetry-card__item">
                  <strong>{{ activeRatioLabel }}</strong>
                  <span>active lots</span>
                </div>
              </div>
            </v-card>
          </v-col>
        </v-row>

        <v-alert v-if="cropStore.error" type="error" variant="tonal" class="mt-4">
          {{ cropStore.error }}
        </v-alert>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import QuickActionButtons from './QuickActionButtons.vue'
import { useCropStore } from '../../stores/useCropStore'
import { useUserStore } from '../../stores/useUserStore'
import type { CropDto, JournalEntryDto } from '../../types/api'

const cropStore = useCropStore()
const userStore = useUserStore()
const router = useRouter()
const { mdAndUp } = useDisplay()
const drawer = ref(true)
const search = ref('')

const orderedStages: CropDto['currentStage'][] = ['seedling', 'veg', 'flower', 'harvest']
const navigationItems = [
  { title: 'Overview', subtitle: 'Live fleet status', icon: 'mdi-view-dashboard-outline' },
  { title: 'Lots', subtitle: 'Cycle tracking', icon: 'mdi-sprout-outline' },
  { title: 'Services', subtitle: 'Health checks', icon: 'mdi-shield-check-outline' },
  { title: 'Analytics', subtitle: 'Yield correlations', icon: 'mdi-chart-box-outline' },
]

const totalLots = computed(() => cropStore.crops.length)
const totalCompletedCycles = computed(() => cropStore.cycleAverages.reduce((sum, item) => sum + item.completedCycles, 0))
const filteredCrops = computed(() => {
  const needle = search.value.trim().toLowerCase()

  if (!needle) {
    return cropStore.crops
  }

  return cropStore.crops.filter((crop) => {
    const haystack = [
      crop.displayName,
      crop.batchCode,
      crop.genetic.code,
      crop.genetic.name,
      crop.currentStage,
    ].join(' ').toLowerCase()

    return haystack.includes(needle)
  })
})
const visibleCrops = computed(() => filteredCrops.value.slice(0, 8))

const averagePh = computed(() => {
  const values = cropStore.latestEntries
    .map((entry) => entry.phLevel?.value)
    .filter((value): value is number => typeof value === 'number')

  if (!values.length) {
    return null
  }

  return values.reduce((sum, value) => sum + value, 0) / values.length
})

const averagePpm = computed(() => {
  const values = cropStore.latestEntries
    .map((entry) => entry.nutrientConcentration?.ppm)
    .filter((value): value is number => typeof value === 'number')

  if (!values.length) {
    return null
  }

  return Math.round(values.reduce((sum, value) => sum + value, 0) / values.length)
})

const stageNodes = computed(() => orderedStages.map((stage) => ({
  key: stage,
  label: stageLabel(stage),
  count: cropStore.crops.filter((crop) => crop.currentStage === stage).length,
})))

const analyticsLeader = computed(() => {
  if (!cropStore.cycleAverages.length) {
    return null
  }

  return [...cropStore.cycleAverages].sort((left, right) => right.completedCycles - left.completedCycles)[0]
})

const maxAverageCycleDays = computed(() => {
  if (!cropStore.cycleAverages.length) {
    return 1
  }

  return Math.max(...cropStore.cycleAverages.map((item) => item.averageCycleDays))
})

const averagePhLabel = computed(() => averagePh.value?.toFixed(2) ?? '—')
const averagePpmLabel = computed(() => averagePpm.value ? `${averagePpm.value} ppm` : '—')
const analyticsLeaderLabel = computed(() => analyticsLeader.value ? analyticsLeader.value.geneticCode : 'No analytics')
const activeRatioLabel = computed(() => totalLots.value ? `${Math.round((cropStore.activeCrops.length / totalLots.value) * 100)}%` : '0%')

const summaryItems = computed(() => [
  { label: 'Active lots', value: cropStore.activeCrops.length },
  { label: 'Harvested', value: cropStore.harvestedCrops.length },
  { label: 'Events', value: cropStore.journalEntries.length },
  { label: 'Avg yield', value: cropStore.averageYield ?? '—' },
])

const serviceItems = computed(() => [
  {
    title: 'Genetics',
    description: analyticsLeaderLabel.value,
    icon: 'mdi-leaf',
    tone: 'primary',
  },
  {
    title: 'Chemistry',
    description: `${averagePhLabel.value} · ${averagePpmLabel.value}`,
    icon: 'mdi-flask-outline',
    tone: 'warning',
  },
  {
    title: 'Journal',
    description: `${cropStore.latestEntries.length} append-only events visibles`,
    icon: 'mdi-book-lock-outline',
    tone: 'success',
  },
])

const syncLabel = computed(() => {
  if (!cropStore.lastSyncedAt) {
    return 'Jamais synchronise'
  }

  return `Synchro ${new Date(cropStore.lastSyncedAt).toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  })}`
})

function analyticsWidth(value: number) {
  return Math.max((value / maxAverageCycleDays.value) * 100, 14)
}

function formatDate(value: string) {
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function stageLabel(stage: CropDto['currentStage'] | string) {
  return {
    seedling: 'Seedling',
    veg: 'Veg',
    flower: 'Flower',
    harvest: 'Harvest',
  }[stage] ?? stage
}

function entryLabel(type: JournalEntryDto['type'] | string) {
  return {
    irrigation: 'Arrosage',
    fertilization: 'Fertilisation',
    environment_check: 'Controle environnement',
    stage_transition: 'Transition de stade',
    observation: 'Observation',
  }[type] ?? type
}

function entryIcon(type: JournalEntryDto['type']) {
  return {
    irrigation: 'mdi-water-outline',
    fertilization: 'mdi-flask-outline',
    environment_check: 'mdi-thermometer-lines',
    stage_transition: 'mdi-swap-horizontal',
    observation: 'mdi-eye-outline',
  }[type]
}

onMounted(async () => {
  await cropStore.loadDashboard()
  cropStore.startRealtimePolling()
})

onUnmounted(() => {
  cropStore.stopRealtimePolling()
})

async function logout() {
  cropStore.stopRealtimePolling()
  userStore.clearSession()
  await router.push({ name: 'auth' })
}
</script>

<style scoped>
:global(body) {
  margin: 0;
  font-family: Roboto, "Helvetica Neue", sans-serif;
  background: #e9eef2;
  color: #37474f;
}

.material-shell {
  background: #e9eef2;
}

.dashboard-drawer {
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  background: linear-gradient(180deg, #304149 0%, #26343b 100%);
  color: #ecf2f5;
}

.drawer-brand {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 20px 20px 16px;
}

.drawer-brand__mark {
  display: grid;
  place-items: center;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.12);
  font-weight: 700;
}

.drawer-brand strong,
.drawer-brand span {
  display: block;
}

.drawer-brand span {
  color: rgba(236, 242, 245, 0.72);
}

.nav-list {
  padding: 0 12px 8px;
}

.drawer-panel {
  margin: 12px 16px 0;
  padding: 14px 14px 10px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.05);
  border: 1px solid rgba(255, 255, 255, 0.06);
}

.drawer-panel__title {
  margin: 0 0 12px;
  color: rgba(236, 242, 245, 0.72);
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.filter-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 8px 0;
}

.filter-row span {
  color: rgba(236, 242, 245, 0.9);
}

.filter-row strong {
  color: white;
}

.filter-row__label {
  display: flex;
  align-items: center;
  gap: 10px;
}

.filter-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

.filter-dot--seedling {
  background: #8bc34a;
}

.filter-dot--veg {
  background: #26c6da;
}

.filter-dot--flower {
  background: #ffb300;
}

.filter-dot--harvest {
  background: #7cb342;
}

.workspace-summary {
  display: grid;
  gap: 6px;
}

.workspace-summary span {
  color: white;
  font-weight: 500;
}

.workspace-summary small {
  color: rgba(236, 242, 245, 0.72);
}

.dashboard-appbar {
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.appbar-search {
  display: flex;
  align-items: center;
  gap: 10px;
  width: min(420px, 100%);
  min-height: 40px;
  padding: 0 14px;
  border-radius: 10px;
  background: rgba(255, 255, 255, 0.08);
  color: rgba(255, 255, 255, 0.74);
}

.appbar-search input {
  width: 100%;
  border: 0;
  outline: 0;
  background: transparent;
  color: white;
  font: inherit;
}

.appbar-search input::placeholder {
  color: rgba(255, 255, 255, 0.62);
}

.dashboard-main {
  background: #e9eef2;
}

.surface-card {
  border: 1px solid #dbe4ea;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(23, 35, 45, 0.06);
}

.updates-card,
.session-card,
.services-card,
.comments-card,
.analytics-card,
.telemetry-card {
  padding: 18px;
}

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.section-header--compact {
  margin-bottom: 14px;
}

.section-header__eyebrow {
  margin: 0 0 4px;
  color: #90a4ae;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.section-header h1,
.section-header h2 {
  margin: 0;
  color: #37474f;
  font-size: 1.5rem;
  font-weight: 500;
}

.section-header h2 {
  font-size: 1.2rem;
}

.summary-strip {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin-bottom: 16px;
  border: 1px solid #e3e8ed;
  border-radius: 10px;
  overflow: hidden;
  background: #f6f8fa;
}

.summary-strip__item {
  padding: 16px;
  text-align: center;
  border-right: 1px solid #e3e8ed;
}

.summary-strip__item:last-child {
  border-right: 0;
}

.summary-strip__item strong {
  display: block;
  color: #00acc1;
  font-size: 1.9rem;
  font-weight: 500;
}

.summary-strip__item span {
  color: #607d8b;
  font-size: 0.92rem;
}

.updates-table {
  display: grid;
  gap: 8px;
}

.updates-table__head {
  display: grid;
  grid-template-columns: minmax(180px, 1.5fr) 110px 110px 140px minmax(220px, 1.4fr);
  gap: 12px;
  padding: 0 14px 8px;
  color: #90a4ae;
  font-size: 0.76rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.updates-row {
  display: grid;
  grid-template-columns: minmax(180px, 1.5fr) 110px 110px 140px minmax(220px, 1.4fr);
  gap: 12px;
  align-items: center;
  padding: 14px;
  border: 1px solid #e4e8ec;
  border-radius: 8px;
  background: #fafbfc;
}

.updates-row__lot {
  display: flex;
  align-items: center;
  gap: 12px;
}

.updates-row__lot strong,
.updates-row__batch strong,
.updates-row__seeded strong,
.service-item strong,
.stream-item strong,
.performance-row strong {
  display: block;
  font-size: 0.96rem;
  font-weight: 600;
}

.updates-row__lot p,
.updates-row__batch small,
.updates-row__seeded small,
.service-item p,
.stream-item p,
.stream-item small,
.performance-row p {
  margin: 0;
  color: #78909c;
}

.lot-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.lot-indicator--seedling {
  background: #8bc34a;
}

.lot-indicator--veg {
  background: #00acc1;
}

.lot-indicator--flower {
  background: #ffb300;
}

.lot-indicator--harvest {
  background: #7cb342;
}

.lot-chip {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 600;
  text-transform: uppercase;
}

.lot-chip--seedling {
  background: #edf7df;
  color: #5c7a24;
}

.lot-chip--veg {
  background: #e0f7fa;
  color: #0b7285;
}

.lot-chip--flower {
  background: #fff7db;
  color: #9a6700;
}

.lot-chip--harvest {
  background: #eef6e3;
  color: #5b7c27;
}

.harvest-pill {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  border-radius: 8px;
  background: #f1f8e9;
  color: #558b2f;
}

.session-form,
.service-list,
.stream-list,
.performance-list {
  display: grid;
  gap: 12px;
}

.service-item,
.stream-item,
.performance-row {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 12px;
  align-items: start;
  padding: 12px;
  border: 1px solid #e4e8ec;
  border-radius: 8px;
  background: #fafbfc;
}

.service-item__icon,
.stream-item__icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: #eceff1;
  color: #546e7a;
}

.service-item__icon--primary {
  background: #e0f7fa;
  color: #00838f;
}

.service-item__icon--warning {
  background: #fff3e0;
  color: #ef6c00;
}

.service-item__icon--success {
  background: #e8f5e9;
  color: #2e7d32;
}

.performance-row {
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
}

.performance-row__bar {
  display: grid;
  gap: 8px;
}

.performance-row__track {
  height: 8px;
  border-radius: 999px;
  background: #e4ebf0;
  overflow: hidden;
}

.performance-row__fill {
  height: 100%;
  background: linear-gradient(90deg, #00acc1, #26c6da);
}

.performance-row small {
  color: #78909c;
}

.telemetry-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.telemetry-card__item {
  padding: 14px;
  border: 1px solid #e4e8ec;
  border-radius: 8px;
  background: #fafbfc;
  text-align: center;
}

.telemetry-card__item strong {
  display: block;
  color: #455a64;
  font-size: 1.35rem;
}

.telemetry-card__item span {
  color: #78909c;
  font-size: 0.84rem;
}

.empty-state {
  padding: 18px;
  border: 1px dashed #ccd6dd;
  border-radius: 8px;
  background: #fafcfd;
  color: #78909c;
  text-align: center;
}

.empty-state--compact {
  padding: 14px;
}

@media (max-width: 1279px) {
  .updates-table__head {
    display: none;
  }

  .updates-row {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 959px) {
  .summary-strip {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .summary-strip__item:nth-child(2) {
    border-right: 0;
  }
}

@media (max-width: 640px) {
  .summary-strip,
  .telemetry-grid {
    grid-template-columns: 1fr;
  }

  .summary-strip__item {
    border-right: 0;
    border-bottom: 1px solid #e3e8ed;
  }

  .summary-strip__item:last-child {
    border-bottom: 0;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .appbar-search {
    width: 100%;
  }

  .performance-row {
    grid-template-columns: 1fr;
  }
}
</style>
