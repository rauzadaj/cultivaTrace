<template>
  <v-app class="material-shell">
    <v-navigation-drawer
      v-model="drawer"
      :permanent="mdAndUp"
      :temporary="!mdAndUp"
      width="250"
      class="dashboard-drawer"
    >
      <div class="drawer-brand">
        <div class="drawer-brand__mark">CT</div>
        <div>
          <strong>CultivaTrace</strong>
          <span>Operations Console</span>
        </div>
      </div>

      <v-list nav density="compact" class="nav-list">
        <v-list-item
          v-for="item in navigationItems"
          :key="item.title"
          :prepend-icon="item.icon"
          :title="item.title"
          :subtitle="item.subtitle"
          active-color="white"
        />
      </v-list>

      <div class="drawer-section">
        <p class="drawer-section__title">Lifecycle</p>
        <div v-for="node in stageNodes" :key="node.key" class="drawer-filter">
          <span>{{ node.label }}</span>
          <strong>{{ node.count }}</strong>
        </div>
      </div>

      <div class="drawer-section">
        <p class="drawer-section__title">Signals</p>
        <div class="drawer-filter">
          <span>Journal entries</span>
          <strong>{{ cropStore.journalEntries.length }}</strong>
        </div>
        <div class="drawer-filter">
          <span>Average pH</span>
          <strong>{{ averagePhLabel }}</strong>
        </div>
        <div class="drawer-filter">
          <span>Avg nutrients</span>
          <strong>{{ averagePpmLabel }}</strong>
        </div>
      </div>
    </v-navigation-drawer>

    <v-app-bar flat color="#2c343a" density="comfortable" class="dashboard-appbar">
      <v-app-bar-nav-icon v-if="!mdAndUp" color="white" @click="drawer = !drawer" />
      <div class="appbar-title">
        <span class="appbar-title__eyebrow">Realtime cultivation</span>
        <strong>Control dashboard</strong>
      </div>
      <v-spacer />
      <v-chip size="small" variant="outlined" color="white" class="mr-2">
        {{ userStore.userEmail || userStore.operatorLabel }}
      </v-chip>
      <v-chip size="small" color="primary" variant="flat" class="mr-2">Fleet active</v-chip>
      <v-chip size="small" variant="outlined" color="white">{{ syncLabel }}</v-chip>
    </v-app-bar>

    <v-main class="dashboard-main">
      <v-container fluid class="pa-4">
        <v-row dense>
          <v-col cols="12" md="8">
            <v-card flat class="surface-card overview-card">
              <div class="section-header">
                <div>
                  <p class="section-header__eyebrow">Updates</p>
                  <h1>Lot activity</h1>
                </div>
                <v-btn color="primary" variant="flat" rounded="lg" :loading="cropStore.loading" @click="cropStore.loadDashboard">
                  Synchroniser
                </v-btn>
              </div>

              <div class="summary-strip">
                <div v-for="item in summaryItems" :key="item.label" class="summary-strip__item">
                  <strong>{{ item.value }}</strong>
                  <span>{{ item.label }}</span>
                </div>
              </div>

              <div v-if="!visibleCrops.length" class="empty-state">Aucun lot disponible.</div>
              <div v-else class="lot-list">
                <article v-for="crop in visibleCrops" :key="crop.id" class="lot-row">
                  <div class="lot-row__primary">
                    <div class="lot-row__state" :class="`lot-row__state--${crop.currentStage}`" />
                    <div>
                      <strong>{{ crop.displayName }}</strong>
                      <p>{{ crop.genetic.code }} · {{ crop.genetic.name }}</p>
                    </div>
                  </div>

                  <div class="lot-row__meta">
                    <span class="lot-chip" :class="`lot-chip--${crop.currentStage}`">{{ stageLabel(crop.currentStage) }}</span>
                    <small>Batch {{ crop.batchCode }}</small>
                    <small>Semis {{ formatDate(crop.seededAt) }}</small>
                  </div>

                  <div class="lot-row__actions">
                    <template v-if="crop.currentStage !== 'harvest'">
                      <QuickActionButtons :crop-iri="crop['@id']" :disabled="cropStore.loading" />
                    </template>
                    <template v-else>
                      <div class="harvest-pill">
                        <v-icon icon="mdi-check-decagram" size="18" />
                        <span>{{ crop.finalYieldGrams ?? '—' }} g recoltes</span>
                      </div>
                    </template>
                  </div>
                </article>
              </div>
            </v-card>
          </v-col>

          <v-col cols="12" md="4">
            <v-card flat class="surface-card session-card mb-4">
              <div class="section-header section-header--compact">
                <div>
                  <p class="section-header__eyebrow">Session</p>
                  <h2>Workspace access</h2>
                </div>
                <v-btn color="secondary" variant="tonal" size="small" @click="logout">Se deconnecter</v-btn>
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

            <v-card flat class="surface-card service-card mb-4">
              <div class="section-header section-header--compact">
                <div>
                  <p class="section-header__eyebrow">Services</p>
                  <h2>System health</h2>
                </div>
              </div>

              <div class="service-list">
                <article class="service-item">
                  <v-icon icon="mdi-leaf" color="primary" />
                  <div>
                    <strong>Genetics</strong>
                    <p>{{ analyticsLeaderLabel }}</p>
                  </div>
                </article>
                <article class="service-item">
                  <v-icon icon="mdi-flask-outline" color="secondary" />
                  <div>
                    <strong>Chemistry</strong>
                    <p>{{ averagePhLabel }} · {{ averagePpmLabel }}</p>
                  </div>
                </article>
                <article class="service-item">
                  <v-icon icon="mdi-book-lock-outline" color="success" />
                  <div>
                    <strong>Journal</strong>
                    <p>{{ cropStore.latestEntries.length }} append-only events visibles</p>
                  </div>
                </article>
              </div>
            </v-card>

            <v-card flat class="surface-card stream-card">
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
          <v-col cols="12" md="8">
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

          <v-col cols="12" md="4">
            <v-card flat class="surface-card telemetry-card">
              <div class="section-header section-header--compact">
                <div>
                  <p class="section-header__eyebrow">Telemetry</p>
                  <h2>Live metrics</h2>
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
                  <span>lots actifs</span>
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

const orderedStages: CropDto['currentStage'][] = ['seedling', 'veg', 'flower', 'harvest']
const navigationItems = [
  { title: 'Overview', subtitle: 'Live fleet status', icon: 'mdi-view-dashboard-outline' },
  { title: 'Lots', subtitle: 'Cycle tracking', icon: 'mdi-sprout-outline' },
  { title: 'Signals', subtitle: 'Journal & chemistry', icon: 'mdi-waveform' },
  { title: 'Analytics', subtitle: 'Yield correlations', icon: 'mdi-chart-box-outline' },
]

const totalLots = computed(() => cropStore.crops.length)
const visibleCrops = computed(() => [...cropStore.activeCrops, ...cropStore.harvestedCrops].slice(0, 8))
const totalCompletedCycles = computed(() => cropStore.cycleAverages.reduce((sum, item) => sum + item.completedCycles, 0))

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
  { label: 'Lots actifs', value: cropStore.activeCrops.length },
  { label: 'Events terrain', value: cropStore.journalEntries.length },
  { label: 'Cycles completes', value: totalCompletedCycles.value },
  { label: 'Rendement moyen', value: cropStore.averageYield ?? '—' },
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
  background: #edf1f4;
  color: #37474f;
}

.material-shell {
  background: #edf1f4;
}

.dashboard-drawer {
  border-right: 1px solid rgba(255, 255, 255, 0.08);
  background: linear-gradient(180deg, #31424b 0%, #2c3a43 100%);
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

.drawer-brand span,
.drawer-section__title,
.drawer-filter span {
  color: rgba(236, 242, 245, 0.72);
}

.nav-list {
  padding: 0 12px;
}

.drawer-section {
  padding: 18px 20px 0;
}

.drawer-section__title {
  margin: 0 0 12px;
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.drawer-filter {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 0;
}

.dashboard-appbar {
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.appbar-title {
  display: grid;
  color: white;
}

.appbar-title__eyebrow {
  font-size: 0.7rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.7);
}

.dashboard-main {
  background: #edf1f4;
}

.surface-card {
  border: 1px solid #d8e0e6;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(23, 35, 45, 0.06);
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

.overview-card,
.session-card,
.service-card,
.stream-card,
.analytics-card,
.telemetry-card {
  padding: 18px;
}

.summary-strip {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  margin-bottom: 14px;
  border: 1px solid #e3e8ed;
  border-radius: 10px;
  overflow: hidden;
  background: #f7f9fb;
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

.lot-list {
  display: grid;
  gap: 10px;
}

.lot-row {
  display: grid;
  gap: 14px;
  padding: 16px;
  border: 1px solid #e4e8ec;
  border-radius: 8px;
  background: #fafbfc;
}

.lot-row__primary {
  display: flex;
  align-items: center;
  gap: 12px;
}

.lot-row__primary strong {
  display: block;
  font-size: 1rem;
  font-weight: 500;
}

.lot-row__primary p,
.lot-row__meta small,
.service-item p,
.stream-item p,
.stream-item small,
.performance-row p {
  margin: 0;
  color: #78909c;
}

.lot-row__state {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.lot-row__state--seedling {
  background: #8bc34a;
}

.lot-row__state--veg {
  background: #00acc1;
}

.lot-row__state--flower {
  background: #ffb300;
}

.lot-row__state--harvest {
  background: #7cb342;
}

.lot-row__meta {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  align-items: center;
}

.lot-chip {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 500;
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

.stream-item__icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: #eceff1;
  color: #546e7a;
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

@media (max-width: 959px) {
  .summary-strip {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .summary-strip__item:nth-child(2) {
    border-right: 0;
  }
}

@media (max-width: 640px) {
  .section-header,
  .lot-row__meta {
    flex-direction: column;
    align-items: flex-start;
  }

  .telemetry-grid,
  .summary-strip {
    grid-template-columns: 1fr;
  }

  .summary-strip__item {
    border-right: 0;
    border-bottom: 1px solid #e3e8ed;
  }

  .summary-strip__item:last-child {
    border-bottom: 0;
  }

  .performance-row {
    grid-template-columns: 1fr;
  }
}
</style>
