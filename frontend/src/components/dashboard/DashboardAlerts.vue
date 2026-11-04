<template>
  <dashboard-panel title="Alertes actives" icon="mdi-alert-circle-outline" :meta="String(alerts.length)">
    <div v-if="alerts.length" class="dashboard-alerts">
      <article v-for="alert in alerts" :key="alert.id" class="dashboard-alert" :class="`dashboard-alert--${alert.severity}`">
        <div class="dashboard-alert__header">
          <q-icon :name="alert.severity === 'critical' ? 'mdi-alert-circle-outline' : 'mdi-alert-outline'" size="16px" />
          <strong>{{ alert.title }}</strong>
          <span class="dashboard-alert__severity">{{ severityLabel(alert.severity) }}</span>
        </div>
        <p>{{ alert.message }}</p>
        <small v-if="alert.context">{{ alert.context }}</small>
      </article>
    </div>
    <div v-else class="dashboard-alerts__empty">
      <q-icon name="mdi-check-circle-outline" size="24px" />
      <strong>Aucune alerte active</strong>
      <p>Aucune alerte signalée dans le dernier aperçu.</p>
    </div>
  </dashboard-panel>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import type { DashboardAlert } from '@/types/api'
import DashboardPanel from './DashboardPanel.vue'

defineProps({ alerts: { type: Array as PropType<DashboardAlert[]>, required: true } })

function severityLabel(severity: DashboardAlert['severity']) {
  if (severity === 'critical') return 'Critique'
  if (severity === 'warning') return 'Attention'
  return 'Information'
}
</script>

<style scoped>
.dashboard-alerts { display: grid; gap: var(--ct-space-2); }
.dashboard-alert { padding: var(--ct-space-3); background: var(--ct-inset); border-radius: var(--ct-radius-md); border-left: 2px solid var(--ct-info); }
.dashboard-alert--critical { border-color: var(--ct-danger); }
.dashboard-alert--warning { border-color: var(--ct-warning); }
.dashboard-alert__header { display: flex; gap: 6px; align-items: center; flex-wrap: wrap; color: var(--ct-info); }
.dashboard-alert--critical .dashboard-alert__header { color: var(--ct-danger); }
.dashboard-alert--warning .dashboard-alert__header { color: var(--ct-warning); }
.dashboard-alert__header strong { flex: 1; font-size: var(--ct-text-md); line-height: var(--ct-line-sm); }
.dashboard-alert__severity { font-size: var(--ct-text-xs); line-height: var(--ct-line-xs); }
.dashboard-alert p { margin: var(--ct-space-2) 0 0; color: var(--ct-text); font-size: var(--ct-text-md); line-height: var(--ct-line-body); overflow-wrap: anywhere; }
.dashboard-alert small { display: block; margin-top: var(--ct-space-1); color: var(--ct-muted); font-family: var(--ct-font-serif); font-size: var(--ct-text-sm); line-height: var(--ct-line-xs); }
.dashboard-alerts__empty { display: grid; justify-items: start; gap: var(--ct-space-2); padding-block: var(--ct-space-3); color: var(--ct-mint); font-size: var(--ct-text-md); }
.dashboard-alerts__empty p { margin: 0; color: var(--ct-muted); line-height: var(--ct-line-body); }
</style>
