<template>
  <div class="dashboard-content">
    <section class="dashboard-metrics" aria-label="Indicateurs de l’organisation">
      <dashboard-metric-card label="Plants actifs" :value="formatNumber(overview.plants.total)" caption="Inventaire actif" icon-src="/design/executive/1-2390/b9371.svg">
        <span class="metric-detail"><span class="metric-detail__dot" />{{ formatNumber(overview.plants.inFlowering) }} en floraison</span>
      </dashboard-metric-card>
      <dashboard-metric-card label="Salles" :value="formatNumber(overview.rooms.total)" caption="Salles de l’organisation" icon="mdi-door-open" tone="info">
        <span>{{ formatNumber(overview.alerts.saturatedRooms) }} à capacité maximale</span>
      </dashboard-metric-card>
      <dashboard-metric-card label="Alertes actives" :value="formatNumber(overview.alerts.items.length)" caption="Capteurs et terrain" icon-src="/design/executive/1-2390/2e981.svg" :tone="overview.alerts.items.length ? 'danger' : 'mint'">
        <span>{{ formatNumber(overview.alerts.sensorsInAlert) }} capteur(s) en alerte</span>
      </dashboard-metric-card>
      <dashboard-metric-card label="Récoltes · 30 jours" :value="formatNumber(overview.harvests.last30Days)" caption="Sur les 30 derniers jours" icon-src="/design/executive/1-2390/41bed.svg" tone="info">
        <span>Récoltes enregistrées</span>
      </dashboard-metric-card>
    </section>
    <div class="dashboard-columns">
      <div class="dashboard-columns__main">
        <dashboard-stage-distribution :counts="overview.plants.byStage" />
        <dashboard-priority-plants :plants="overview.overview.spotlightPlants" :mobile="mobile" />
      </div>
      <div class="dashboard-columns__aside">
        <dashboard-alerts :alerts="overview.alerts.items" />
        <dashboard-activity-log :entries="overview.overview.recentEvents" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import type { DashboardOverviewResponse } from '@/types/api'
import DashboardMetricCard from './DashboardMetricCard.vue'
import DashboardStageDistribution from './DashboardStageDistribution.vue'
import DashboardPriorityPlants from './DashboardPriorityPlants.vue'
import DashboardAlerts from './DashboardAlerts.vue'
import DashboardActivityLog from './DashboardActivityLog.vue'

defineProps({
  overview: { type: Object as PropType<DashboardOverviewResponse>, required: true },
  mobile: { type: Boolean, default: false },
})
const formatNumber = (value: number) => value.toLocaleString('fr-FR')
</script>

<style scoped>
.dashboard-content { display: grid; gap: var(--ct-space-6); min-width: 0; }
.dashboard-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--ct-space-4); }
.metric-detail { display: flex; align-items: center; gap: var(--ct-space-2); }
.metric-detail__dot { width: 6px; height: 6px; flex-shrink: 0; border-radius: 50%; background: var(--ct-mint); }
.dashboard-columns { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); gap: var(--ct-space-6); align-items: start; }
.dashboard-columns__main, .dashboard-columns__aside { display: grid; gap: var(--ct-space-6); min-width: 0; }
@media (max-width: 1199px) {
  .dashboard-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .dashboard-columns { grid-template-columns: 1fr; }
  .dashboard-columns__aside { grid-template-columns: repeat(2, minmax(0, 1fr)); align-items: start; }
}
@media (max-width: 767px) {
  .dashboard-columns__aside { grid-template-columns: 1fr; }
  .dashboard-content, .dashboard-columns, .dashboard-columns__main, .dashboard-columns__aside { gap: var(--ct-space-4); }
  .dashboard-metrics { gap: var(--ct-space-3); }
}
@media (max-width: 359px) {
  .dashboard-metrics { grid-template-columns: 1fr; }
}
</style>
