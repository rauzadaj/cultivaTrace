<template>
  <div class="dashboard-view" :aria-busy="loading">
    <header class="dashboard-header">
      <div class="dashboard-header__copy">
        <div class="dashboard-header__context">
          <span class="dashboard-header__organization">{{ organizationName }}</span>
          <span v-if="overview && !loadError" class="dashboard-header__snapshot">
            <q-icon name="mdi-clock-outline" size="12px" />
            Aperçu du {{ snapshotLabel }}
          </span>
        </div>
        <h1>Tableau de bord exécutif</h1>
        <p class="dashboard-header__description">Suivi des plants, des salles et des dernières opérations.</p>
        <p class="dashboard-header__greeting">Bonjour {{ firstName }} <span aria-hidden="true">·</span> {{ todayLabel }}</p>
      </div>
      <div class="dashboard-header__actions">
        <q-btn flat no-caps icon="mdi-refresh" label="Actualiser" class="dashboard-refresh" :loading="loading" @click="fetchOverview" />
        <q-btn unelevated no-caps icon="mdi-plus" label="Nouvelle ferme" class="dashboard-farm-btn" @click="farmDialogOpen = true" />
      </div>
    </header>

    <div v-if="loadError" class="dashboard-error" role="alert">
      <q-icon name="mdi-alert-circle-outline" size="20px" />
      <div>
        <strong>{{ overview ? 'Actualisation impossible' : 'Tableau de bord indisponible' }}</strong>
        <p>{{ loadError }}</p>
        <p v-if="overview">Les dernières données reçues sont conservées. Aperçu du {{ snapshotLabel }}.</p>
      </div>
      <q-btn flat no-caps label="Réessayer" :loading="loading" @click="fetchOverview" />
    </div>

    <div v-if="loading && !overview" class="dashboard-skeleton" role="status" aria-label="Chargement du tableau de bord">
      <div class="dashboard-skeleton__metrics">
        <q-skeleton v-for="index in 4" :key="index" height="190px" class="dashboard-skeleton__item" />
      </div>
      <q-skeleton height="180px" class="dashboard-skeleton__item" />
      <q-skeleton height="240px" class="dashboard-skeleton__item" />
    </div>

    <template v-else-if="overview">
      <q-pull-to-refresh v-if="isMobile" class="dashboard-mobile" @refresh="refreshDashboard">
        <dashboard-overview-content :overview="overview" mobile />
      </q-pull-to-refresh>
      <dashboard-overview-content v-else :overview="overview" />
    </template>

    <farm-form v-model="farmDialogOpen" @created="handleFarmCreated" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { dashboardApi } from '@/services/api'
import type { DashboardOverviewResponse } from '@/types/api'
import FarmForm from '@/components/farms/FarmForm.vue'
import DashboardOverviewContent from '@/components/dashboard/DashboardOverviewContent.vue'
import { useDisplay } from '@/composables/useDisplay'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const { xs } = useDisplay()
const isMobile = computed(() => xs.value)
const farmDialogOpen = ref(false)
const loading = ref(true)
const loadError = ref<string | null>(null)
const overview = ref<DashboardOverviewResponse | null>(null)
const organizationName = computed(() => overview.value?.organization.name || authStore.organization?.name || 'Vue d’ensemble')
const firstName = computed(() => (authStore.user?.email || 'opérateur').split(/[\s@._-]+/)[0] || 'opérateur')
const todayLabel = computed(() => new Date().toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' }))
const snapshotLabel = computed(() => {
  if (!overview.value?.generatedAt) return 'date indisponible'
  const date = new Date(overview.value.generatedAt)
  if (Number.isNaN(date.getTime())) return 'date indisponible'
  return date.toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
})

async function fetchOverview(): Promise<void> {
  loading.value = true
  loadError.value = null
  try {
    const { data } = await dashboardApi.overview()
    overview.value = data
  } catch (error) {
    loadError.value = error instanceof Error ? error.message : 'Impossible de charger le tableau de bord.'
  } finally {
    loading.value = false
  }
}

onMounted(fetchOverview)

async function handleFarmCreated(): Promise<void> {
  await fetchOverview()
}

async function refreshDashboard(done: () => void): Promise<void> {
  try {
    await fetchOverview()
  } finally {
    done()
  }
}
</script>

<style scoped>
.dashboard-view { display: grid; gap: var(--ct-space-6); min-width: 0; font-family: var(--ct-font-sans); color: var(--ct-text); }
.dashboard-header { display: flex; justify-content: space-between; align-items: flex-end; gap: var(--ct-space-4); padding-bottom: var(--ct-space-4); }
.dashboard-header__copy { min-width: 0; }
.dashboard-header__context { display: flex; align-items: center; flex-wrap: wrap; gap: var(--ct-space-2); margin-bottom: var(--ct-space-1); font-family: var(--ct-font-serif); font-size: var(--ct-text-sm); line-height: var(--ct-line-xs); }
.dashboard-header__organization { color: var(--ct-muted); text-transform: uppercase; letter-spacing: 0.55px; overflow-wrap: anywhere; }
.dashboard-header__snapshot { display: inline-flex; align-items: center; gap: 6px; padding: 2px var(--ct-space-2); background: var(--ct-raised); color: var(--ct-secondary); border-radius: var(--ct-radius-md); }
.dashboard-header h1 { margin: 0; font-size: var(--ct-text-heading); font-weight: 600; line-height: var(--ct-line-heading); letter-spacing: -0.8px; }
.dashboard-header__description { margin: var(--ct-space-1) 0 0; color: var(--ct-secondary); font-size: var(--ct-text-body); line-height: var(--ct-line-body); }
.dashboard-header__greeting { margin: var(--ct-space-1) 0 0; color: var(--ct-muted); font-family: var(--ct-font-serif); font-size: var(--ct-text-sm); line-height: var(--ct-line-xs); }
.dashboard-header__actions { display: flex; gap: var(--ct-space-2); align-items: center; flex-shrink: 0; flex-wrap: wrap; }
.dashboard-header__actions :deep(.q-btn) { min-height: 36px; border-radius: var(--ct-radius-md); font-family: var(--ct-font-sans); font-size: var(--ct-text-md); line-height: var(--ct-line-sm); padding: var(--ct-space-2) var(--ct-space-3); }
.dashboard-header__actions :deep(.q-icon) { font-size: 16px; }
.dashboard-refresh { color: var(--ct-text); background: var(--ct-raised); }
.dashboard-farm-btn { background: var(--ct-primary); color: var(--ct-on-primary); font-weight: 600; }
.dashboard-error { display: flex; align-items: flex-start; gap: var(--ct-space-3); padding: var(--ct-space-4); background: var(--ct-danger-soft); color: var(--ct-danger); border: 1px solid var(--ct-danger); border-radius: var(--ct-radius-lg); font-size: var(--ct-text-body); }
.dashboard-error > div { flex: 1; min-width: 0; }
.dashboard-error p { margin: var(--ct-space-1) 0 0; line-height: var(--ct-line-body); overflow-wrap: anywhere; }
.dashboard-error :deep(.q-btn) { flex-shrink: 0; min-height: 36px; font-size: var(--ct-text-md); }
.dashboard-skeleton { display: grid; gap: var(--ct-space-6); }
.dashboard-skeleton__metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--ct-space-4); }
.dashboard-skeleton__item { background: var(--ct-surface); border-radius: var(--ct-radius-lg); }
@media (max-width: 1279px) {
  .dashboard-header { align-items: flex-start; flex-direction: column; }
}
@media (max-width: 1199px) {
  .dashboard-skeleton__metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 767px) {
  .dashboard-view { gap: var(--ct-space-4); }
  .dashboard-header { padding-bottom: 0; }
  .dashboard-header h1 { font-size: 26px; line-height: 34px; }
  .dashboard-header__actions :deep(.q-btn), .dashboard-error :deep(.q-btn) { min-height: 44px; }
  .dashboard-error { flex-wrap: wrap; }
  .dashboard-error > div { min-width: 180px; }
  .dashboard-skeleton__metrics { gap: var(--ct-space-3); }
}
</style>
