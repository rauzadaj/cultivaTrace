<template>
  <dashboard-panel title="Journal récent" icon-src="/design/executive/1-2926/2be0e.svg" :meta="`${entries.length} événements`">
    <ol v-if="entries.length" class="activity-log">
      <li v-for="entry in entries" :key="entry.id" class="activity-log__item">
        <div class="activity-log__header">
          <strong>{{ eventLabel(entry.eventType) }}</strong>
          <time :datetime="entry.occurredAt">{{ formatDateTime(entry.occurredAt) }}</time>
        </div>
        <p>{{ entry.notes || eventContext(entry) }}</p>
      </li>
    </ol>
    <p v-else class="activity-log__empty">Aucun événement récent.</p>
  </dashboard-panel>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import { PLANT_STAGE_LABELS, type DashboardRecentEvent, type PlantStage } from '@/types/api'
import DashboardPanel from './DashboardPanel.vue'

defineProps({ entries: { type: Array as PropType<DashboardRecentEvent[]>, required: true } })

function eventLabel(type: DashboardRecentEvent['eventType']): string {
  const labels: Record<DashboardRecentEvent['eventType'], string> = {
    germination: 'Germination',
    stage_change: 'Changement de stade',
    note: 'Observation',
    photo: 'Photo',
    input_record: 'Intrant',
    harvest: 'Récolte',
    destruction_intent: 'Demande de destruction',
    destruction_confirmed: 'Destruction confirmée',
    room_move: 'Changement de salle',
    legacy_activity: 'Activité',
  }
  return labels[type] ?? type
}

function eventContext(entry: DashboardRecentEvent): string {
  if (entry.eventType === 'stage_change' && entry.payload && 'to' in entry.payload) {
    const stage = String(entry.payload.to)
    return `Passage au stade ${PLANT_STAGE_LABELS[stage as PlantStage] ?? stage}`
  }
  if (entry.eventType === 'germination') return 'Mise en culture du plant'
  return 'Événement terrain'
}

function formatDateTime(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Date indisponible'
  return date.toLocaleString('fr-FR', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.activity-log { display: grid; gap: var(--ct-space-2); padding: 0; margin: 0; list-style: none; }
.activity-log__item { padding: 10px; background: var(--ct-inset); border-radius: var(--ct-radius-md); }
.activity-log__header { display: flex; justify-content: space-between; gap: var(--ct-space-2); flex-wrap: wrap; font-family: var(--ct-font-serif); font-size: var(--ct-text-xs); line-height: 15px; }
.activity-log__header strong { color: var(--ct-mint); }
.activity-log__header time { color: var(--ct-muted); }
.activity-log__item p { margin: var(--ct-space-1) 0 0; color: var(--ct-text); font-size: var(--ct-text-md); line-height: 18px; overflow-wrap: anywhere; }
.activity-log__empty { margin: 0; color: var(--ct-secondary); font-size: var(--ct-text-md); line-height: var(--ct-line-body); }
</style>
