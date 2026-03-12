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
          :to="{ name: item.to }"
          rounded="lg"
          active-color="white"
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

      <div class="appbar-title">
        <span class="appbar-title__eyebrow">{{ pageKicker }}</span>
        <strong>{{ pageTitle }}</strong>
      </div>

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
      <v-chip size="small" variant="flat" color="primary" class="mr-2">{{ activeMenuLabel }}</v-chip>
      <v-chip size="small" variant="outlined" color="white" class="mr-2">{{ userStore.operatorLabel }}</v-chip>
      <v-btn size="small" variant="text" color="white" @click="logout">Logout</v-btn>
    </v-app-bar>

    <v-main class="dashboard-main">
      <v-container fluid class="pa-4">
        <template v-if="isOverviewView">
          <v-row dense>
            <v-col cols="12" lg="8">
              <LotsPanel :items="visibleCrops" :summary-items="summaryItems" :loading="cropStore.loading" @refresh="cropStore.loadDashboard" />
            </v-col>
            <v-col cols="12" lg="4">
              <WorkspaceCard
                :api-base-url="userStore.apiBaseUrl"
                :operator-label="userStore.operatorLabel"
                :user-email="userStore.userEmail"
                @update:api-base-url="userStore.setApiBaseUrl"
                @update:operator-label="userStore.setOperatorLabel"
              />
              <ServicesCard :items="serviceItems" class="mb-4" />
              <CommentsCard :entries="cropStore.latestEntries" />
            </v-col>
          </v-row>

          <v-row dense class="mt-1">
            <v-col cols="12" lg="8">
              <AnalyticsCard :items="cropStore.cycleAverages" :analytics-width="analyticsWidth" />
            </v-col>
            <v-col cols="12" lg="4">
              <TelemetryCard
                :journal-count="cropStore.journalEntries.length"
                :average-yield="cropStore.averageYield"
                :average-ph-label="averagePhLabel"
                :active-ratio-label="activeRatioLabel"
              />
            </v-col>
          </v-row>
        </template>

        <template v-else-if="isLotsView">
          <v-row dense>
            <v-col cols="12" lg="9">
              <LotsPanel
                :items="filteredCrops"
                :summary-items="summaryItems"
                :loading="cropStore.loading"
                show-count
                count-label="visibles"
                @refresh="cropStore.loadDashboard"
              />
            </v-col>
            <v-col cols="12" lg="3">
              <WorkspaceCard
                :api-base-url="userStore.apiBaseUrl"
                :operator-label="userStore.operatorLabel"
                :user-email="userStore.userEmail"
                @update:api-base-url="userStore.setApiBaseUrl"
                @update:operator-label="userStore.setOperatorLabel"
              />
              <TelemetryCard
                :journal-count="cropStore.journalEntries.length"
                :average-yield="cropStore.averageYield"
                :average-ph-label="averagePhLabel"
                :active-ratio-label="activeRatioLabel"
              />
            </v-col>
          </v-row>
        </template>

        <template v-else-if="isServicesView">
          <v-row dense>
            <v-col cols="12" lg="8">
              <ServicesCard title="Operational services" dense :items="serviceItems" />
              <CommentsCard :entries="cropStore.latestEntries" class="mt-4" />
            </v-col>
            <v-col cols="12" lg="4">
              <WorkspaceCard
                :api-base-url="userStore.apiBaseUrl"
                :operator-label="userStore.operatorLabel"
                :user-email="userStore.userEmail"
                @update:api-base-url="userStore.setApiBaseUrl"
                @update:operator-label="userStore.setOperatorLabel"
              />
              <TelemetryCard
                :journal-count="cropStore.journalEntries.length"
                :average-yield="cropStore.averageYield"
                :average-ph-label="averagePhLabel"
                :active-ratio-label="activeRatioLabel"
              />
            </v-col>
          </v-row>
        </template>

        <template v-else>
          <v-row dense>
            <v-col cols="12" lg="8">
              <AnalyticsCard :items="cropStore.cycleAverages" :analytics-width="analyticsWidth" />
            </v-col>
            <v-col cols="12" lg="4">
              <ServicesCard :items="serviceItems" class="mb-4" />
              <TelemetryCard
                :journal-count="cropStore.journalEntries.length"
                :average-yield="cropStore.averageYield"
                :average-ph-label="averagePhLabel"
                :active-ratio-label="activeRatioLabel"
              />
            </v-col>
          </v-row>
        </template>

        <v-alert v-if="cropStore.error" type="error" variant="tonal" class="mt-4">
          {{ cropStore.error }}
        </v-alert>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useDisplay } from 'vuetify'
import QuickActionButtons from './QuickActionButtons.vue'
import WorkspaceCard from './WorkspaceCard.vue'
import ServicesCard from './ServicesCard.vue'
import CommentsCard from './CommentsCard.vue'
import AnalyticsCard from './AnalyticsCard.vue'
import TelemetryCard from './TelemetryCard.vue'
import { useCropStore } from '../../stores/useCropStore'
import { useUserStore } from '../../stores/useUserStore'
import type { CropDto, JournalEntryDto } from '../../types/api'

const cropStore = useCropStore()
const userStore = useUserStore()
const route = useRoute()
const router = useRouter()
const { mdAndUp } = useDisplay()
const drawer = ref(true)
const search = ref('')

const orderedStages: CropDto['currentStage'][] = ['seedling', 'veg', 'flower', 'harvest']
const navigationItems = [
  { title: 'Overview', subtitle: 'Live fleet status', icon: 'mdi-view-dashboard-outline', to: 'dashboard-overview' },
  { title: 'Lots', subtitle: 'Cycle tracking', icon: 'mdi-sprout-outline', to: 'dashboard-lots' },
  { title: 'Services', subtitle: 'Health checks', icon: 'mdi-shield-check-outline', to: 'dashboard-services' },
  { title: 'Analytics', subtitle: 'Yield correlations', icon: 'mdi-chart-box-outline', to: 'dashboard-analytics' },
]

const isOverviewView = computed(() => route.name === 'dashboard-overview')
const isLotsView = computed(() => route.name === 'dashboard-lots')
const isServicesView = computed(() => route.name === 'dashboard-services')
const isAnalyticsView = computed(() => route.name === 'dashboard-analytics')

const pageTitle = computed(() => {
  if (isLotsView.value) return 'Lot operations'
  if (isServicesView.value) return 'Service center'
  if (isAnalyticsView.value) return 'Analytics center'
  return 'Control dashboard'
})

const pageKicker = computed(() => {
  if (isLotsView.value) return 'Tracked batches'
  if (isServicesView.value) return 'Operational services'
  if (isAnalyticsView.value) return 'Genetic correlations'
  return 'Realtime cultivation'
})

const activeMenuLabel = computed(() => navigationItems.find((item) => item.to === route.name)?.title ?? 'Overview')
const totalLots = computed(() => cropStore.crops.length)
const totalCompletedCycles = computed(() => cropStore.cycleAverages.reduce((sum, item) => sum + item.completedCycles, 0))

const filteredCrops = computed(() => {
  const needle = search.value.trim().toLowerCase()
  if (!needle) return cropStore.crops

  return cropStore.crops.filter((crop) => {
    const haystack = [crop.displayName, crop.batchCode, crop.genetic.code, crop.genetic.name, crop.currentStage].join(' ').toLowerCase()
    return haystack.includes(needle)
  })
})

const visibleCrops = computed(() => filteredCrops.value.slice(0, 6))

const averagePh = computed(() => {
  const values = cropStore.latestEntries.map((entry) => entry.phLevel?.value).filter((value): value is number => typeof value === 'number')
  if (!values.length) return null
  return values.reduce((sum, value) => sum + value, 0) / values.length
})

const averagePpm = computed(() => {
  const values = cropStore.latestEntries.map((entry) => entry.nutrientConcentration?.ppm).filter((value): value is number => typeof value === 'number')
  if (!values.length) return null
  return Math.round(values.reduce((sum, value) => sum + value, 0) / values.length)
})

const stageNodes = computed(() => orderedStages.map((stage) => ({
  key: stage,
  label: stageLabel(stage),
  count: cropStore.crops.filter((crop) => crop.currentStage === stage).length,
})))

const analyticsLeader = computed(() => {
  if (!cropStore.cycleAverages.length) return null
  return [...cropStore.cycleAverages].sort((left, right) => right.completedCycles - left.completedCycles)[0]
})

const maxAverageCycleDays = computed(() => cropStore.cycleAverages.length ? Math.max(...cropStore.cycleAverages.map((item) => item.averageCycleDays)) : 1)
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
    tone: 'primary' as const,
    status: analyticsLeaderLabel.value,
  },
  {
    title: 'Chemistry',
    description: `${averagePhLabel.value} · ${averagePpmLabel.value}`,
    icon: 'mdi-flask-outline',
    tone: 'warning' as const,
    status: averagePhLabel.value,
  },
  {
    title: 'Journal',
    description: `${cropStore.latestEntries.length} append-only events visibles`,
    icon: 'mdi-book-lock-outline',
    tone: 'success' as const,
    status: `${cropStore.journalEntries.length} events`,
  },
])

const syncLabel = computed(() => {
  if (!cropStore.lastSyncedAt) return 'Jamais synchronise'
  return `Synchro ${new Date(cropStore.lastSyncedAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`
})

function analyticsWidth(value: number) {
  return Math.max((value / maxAverageCycleDays.value) * 100, 14)
}

function stageLabel(stage: CropDto['currentStage'] | string) {
  return { seedling: 'Seedling', veg: 'Veg', flower: 'Flower', harvest: 'Harvest' }[stage] ?? stage
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

<script lang="ts">
import { defineComponent, PropType } from 'vue'
import { VBtn, VCard, VChip, VIcon } from 'vuetify/components'
import QuickActionButtons from './QuickActionButtons.vue'
import type { CropDto } from '../../types/api'

export const LotsPanel = defineComponent({
  name: 'LotsPanel',
  components: {
    QuickActionButtons,
    VBtn,
    VCard,
    VChip,
    VIcon,
  },
  props: {
    items: {
      type: Array as PropType<CropDto[]>,
      required: true,
    },
    summaryItems: {
      type: Array as PropType<Array<{ label: string; value: string | number | null }>>,
      required: true,
    },
    loading: {
      type: Boolean,
      required: true,
    },
    showCount: {
      type: Boolean,
      default: false,
    },
    countLabel: {
      type: String,
      default: '',
    },
  },
  emits: ['refresh'],
  methods: {
    formatDate(value: string) {
      return new Date(value).toLocaleString('fr-FR', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
      })
    },
    stageLabel(stage: CropDto['currentStage']) {
      return { seedling: 'Seedling', veg: 'Veg', flower: 'Flower', harvest: 'Harvest' }[stage]
    },
  },
  template: `
    <v-card flat class="surface-card updates-card">
      <div class="section-header">
        <div>
          <p class="section-header__eyebrow">Updates</p>
          <h1>Lot operations</h1>
        </div>
        <div class="section-actions">
          <v-chip v-if="showCount" size="small" variant="tonal" color="primary">{{ items.length }} {{ countLabel }}</v-chip>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="loading" @click="$emit('refresh')">Refresh</v-btn>
        </div>
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

        <div v-if="!items.length" class="empty-state">Aucun lot disponible pour ce filtre.</div>

        <article v-for="crop in items" :key="crop.id" class="updates-row">
          <div class="updates-row__lot">
            <span class="lot-indicator" :class="\`lot-indicator--\${crop.currentStage}\`" />
            <div>
              <strong>{{ crop.displayName }}</strong>
              <p>{{ crop.genetic.code }} · {{ crop.genetic.name }}</p>
            </div>
          </div>

          <div class="updates-row__stage">
            <span class="lot-chip" :class="\`lot-chip--\${crop.currentStage}\`">{{ stageLabel(crop.currentStage) }}</span>
          </div>

          <div class="updates-row__batch">
            <strong>{{ crop.batchCode }}</strong>
            <small>{{ crop.currentStage === 'harvest' ? 'closed cycle' : 'active cycle' }}</small>
          </div>

          <div class="updates-row__seeded">
            <strong>{{ formatDate(crop.seededAt) }}</strong>
            <small>{{ crop.harvestedAt ? \`Harvest \${formatDate(crop.harvestedAt)}\` : 'In progress' }}</small>
          </div>

          <div class="updates-row__action">
            <template v-if="crop.currentStage !== 'harvest'">
              <QuickActionButtons :crop-iri="crop['@id']" :disabled="loading" />
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
  `,
})
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
  gap: 16px;
  border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.appbar-title {
  display: grid;
  min-width: 180px;
  color: white;
}

.appbar-title__eyebrow {
  font-size: 0.7rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(255, 255, 255, 0.7);
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

.updates-card {
  padding: 18px;
}

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.section-header__eyebrow {
  margin: 0 0 4px;
  color: #90a4ae;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.section-header h1 {
  margin: 0;
  color: #37474f;
  font-size: 1.5rem;
  font-weight: 500;
}

.section-actions {
  display: flex;
  align-items: center;
  gap: 10px;
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
.updates-row__seeded strong {
  display: block;
  font-size: 0.96rem;
  font-weight: 600;
}

.updates-row__lot p,
.updates-row__batch small,
.updates-row__seeded small {
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

.empty-state {
  padding: 18px;
  border: 1px dashed #ccd6dd;
  border-radius: 8px;
  background: #fafcfd;
  color: #78909c;
  text-align: center;
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

  .section-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .dashboard-appbar {
    gap: 8px;
  }

  .appbar-search {
    width: 100%;
  }
}
</style>
