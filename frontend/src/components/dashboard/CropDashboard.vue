<template>
  <v-app class="material-shell">
    <v-navigation-drawer
      v-model="drawer"
      :permanent="mdAndUp"
      :temporary="!mdAndUp"
      width="288"
      class="dashboard-drawer"
    >
      <div class="drawer-brand">
        <img src="/cultivatrace.png" alt="CultivaTrace" class="drawer-brand__logo">
        <div>
          <strong>CultivaTrace</strong>
          <span>Agricultural traceability console</span>
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
          color="white"
        />
      </v-list>

      <div class="drawer-panel">
        <p class="drawer-panel__title">Fleet snapshot</p>
        <div v-for="metric in sidebarMetrics" :key="metric.label" class="filter-row">
          <span>{{ metric.label }}</span>
          <strong>{{ metric.value }}</strong>
        </div>
      </div>

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
        <img src="/cultivatrace.png" alt="CultivaTrace" class="appbar-title__logo">
        <div>
          <span class="appbar-title__eyebrow">{{ pageKicker }}</span>
          <strong>{{ pageTitle }}</strong>
        </div>
      </div>

      <div class="appbar-search">
        <v-icon icon="mdi-magnify" size="18" />
        <input
          v-model.trim="search"
          type="text"
          placeholder="Find lot, genetic or service"
          aria-label="Search workspace"
        >
      </div>

      <v-spacer />
      <v-chip size="small" variant="flat" color="primary" class="mr-2">{{ activeMenuLabel }}</v-chip>
      <v-chip size="small" variant="outlined" color="white" class="mr-2">{{ userStore.operatorLabel }}</v-chip>
      <v-btn size="small" variant="text" color="white" @click="logout">Logout</v-btn>
    </v-app-bar>

    <v-main class="dashboard-main">
      <v-container fluid class="pa-4">
        <OverviewSection v-if="route.name === 'dashboard-overview'" :search="search" />
        <LotsSection v-else-if="route.name === 'dashboard-lots'" :search="search" />
        <ServicesSection v-else-if="route.name === 'dashboard-services'" :search="search" />
        <AnalyticsSection v-else-if="route.name === 'dashboard-analytics'" :search="search" />
        <RoadmapSection v-else :search="search" />

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
import AnalyticsSection from './AnalyticsSection.vue'
import LotsSection from './LotsSection.vue'
import OverviewSection from './OverviewSection.vue'
import ServicesSection from './ServicesSection.vue'
import RoadmapSection from './RoadmapSection.vue'
import { useCropStore } from '../../stores/useCropStore'
import { useUserStore } from '../../stores/useUserStore'
import type { CropDto } from '../../types/api'

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
  { title: 'Roadmap', subtitle: 'Delivery milestones', icon: 'mdi-map-marker-path', to: 'dashboard-roadmap' },
] as const

const pageTitle = computed(() => {
  if (route.name === 'dashboard-lots') return 'Lot operations'
  if (route.name === 'dashboard-services') return 'Service center'
  if (route.name === 'dashboard-analytics') return 'Analytics center'
  if (route.name === 'dashboard-roadmap') return 'Execution roadmap'

  return 'Control dashboard'
})

const pageKicker = computed(() => {
  if (route.name === 'dashboard-lots') return 'Tracked batches'
  if (route.name === 'dashboard-services') return 'Operational services'
  if (route.name === 'dashboard-analytics') return 'Genetic correlations'
  if (route.name === 'dashboard-roadmap') return 'Program delivery'

  return 'Realtime cultivation'
})

const activeMenuLabel = computed(() => navigationItems.find((item) => item.to === route.name)?.title ?? 'Overview')
const averagePhLabel = computed(() => {
  const values = cropStore.latestEntries.map((entry) => entry.phLevel?.value).filter((value): value is number => typeof value === 'number')

  if (!values.length) {
    return '—'
  }

  return (values.reduce((sum, value) => sum + value, 0) / values.length).toFixed(2)
})

const sidebarMetrics = computed(() => [
  { label: 'Lots', value: cropStore.crops.length },
  { label: 'Genetics', value: cropStore.genetics.length },
  { label: 'Services', value: cropStore.services.length },
  { label: 'Average pH', value: averagePhLabel.value },
])

const stageNodes = computed(() => orderedStages.map((stage) => ({
  key: stage,
  label: {
    seedling: 'Seedling',
    veg: 'Veg',
    flower: 'Flower',
    harvest: 'Harvest',
  }[stage],
  count: cropStore.crops.filter((crop) => crop.currentStage === stage).length,
})))

const syncLabel = computed(() => {
  if (!cropStore.lastSyncedAt) {
    return 'Never synced'
  }

  return `Synced ${new Date(cropStore.lastSyncedAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`
})

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
.material-shell,
.dashboard-main {
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
  padding: 20px;
}

.drawer-brand__logo {
  width: 46px;
  height: 46px;
  border-radius: 12px;
  object-fit: cover;
  box-shadow: 0 8px 18px rgba(0, 0, 0, 0.22);
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
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 220px;
  color: white;
}

.appbar-title__logo {
  width: 34px;
  height: 34px;
  border-radius: 10px;
  object-fit: cover;
}

.appbar-title__eyebrow {
  display: block;
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

@media (max-width: 640px) {
  .dashboard-appbar {
    gap: 8px;
  }

  .appbar-search {
    width: 100%;
  }
}
</style>
