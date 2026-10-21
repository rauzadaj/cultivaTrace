<template>
  <dashboard-panel title="Plants prioritaires" icon="mdi-sprout-outline" :meta="`${plants.length} ${plants.length === 1 ? 'plant' : 'plants'}`">
    <template v-if="plants.length">
      <div v-if="mobile" class="priority-cards" data-testid="priority-cards">
        <article v-for="plant in plants" :key="plant.id" class="priority-card">
          <div class="priority-card__header">
            <strong>{{ plant.name }}</strong>
            <q-badge class="priority-stage" :style="stageStyle(plant.stage)">{{ stageLabel(plant.stage) }}</q-badge>
          </div>
          <p>{{ plant.strain }}</p>
          <div class="priority-card__meta"><span>{{ plant.room }}</span><span>{{ plant.ageInDays }} j</span></div>
        </article>
      </div>
      <div v-else class="priority-table-wrap">
        <table class="priority-table">
          <caption class="visually-hidden">Plants prioritaires par stade, salle et âge</caption>
          <thead><tr><th scope="col">Plant / variété</th><th scope="col">Stade</th><th scope="col">Salle</th><th scope="col">Âge</th></tr></thead>
          <tbody>
            <tr v-for="plant in plants" :key="plant.id">
              <th scope="row"><strong>{{ plant.name }}</strong><small>{{ plant.strain }}</small></th>
              <td><q-badge class="priority-stage" :style="stageStyle(plant.stage)">{{ stageLabel(plant.stage) }}</q-badge></td>
              <td>{{ plant.room }}</td>
              <td class="priority-table__age">{{ plant.ageInDays }} j</td>
            </tr>
          </tbody>
        </table>
      </div>
    </template>
    <p v-else class="priority-empty">Aucun plant prioritaire à afficher.</p>
  </dashboard-panel>
</template>

<script setup lang="ts">
import type { PropType } from 'vue'
import { PLANT_STAGE_LABELS, type DashboardSpotlightPlant, type PlantStage } from '@/types/api'
import DashboardPanel from './DashboardPanel.vue'

defineProps({
  plants: { type: Array as PropType<DashboardSpotlightPlant[]>, required: true },
  mobile: { type: Boolean, default: false },
})
const stageLabel = (stage: PlantStage) => PLANT_STAGE_LABELS[stage] ?? stage
const stageStyle = (stage: PlantStage) => ({ color: stage === 'harvest' ? 'var(--ct-neutral)' : `var(--ct-stage-${stage})` })
</script>

<style scoped>
.priority-table-wrap { overflow-x: auto; }
.priority-table { width: 100%; border-collapse: collapse; text-align: left; font-size: var(--ct-text-md); line-height: var(--ct-line-body); }
.priority-table thead { color: var(--ct-muted); font-size: var(--ct-text-xs); text-transform: uppercase; letter-spacing: 0.4px; }
.priority-table th, .priority-table td { padding: var(--ct-space-3) var(--ct-space-2); border-bottom: 1px solid var(--ct-border); }
.priority-table th:first-child, .priority-table td:first-child { padding-left: 0; }
.priority-table th:last-child, .priority-table td:last-child { padding-right: 0; }
.priority-table thead th { font-weight: 500; padding-top: var(--ct-space-1); }
.priority-table tbody tr:last-child > * { border-bottom: 0; }
.priority-table tbody th { min-width: 100px; font-weight: 500; overflow-wrap: anywhere; }
.priority-table tbody th strong { font-weight: 500; }
.priority-table td { color: var(--ct-secondary); }
.priority-table small { display: block; color: var(--ct-muted); font-family: var(--ct-font-serif); font-size: var(--ct-text-sm); line-height: var(--ct-line-sm); }
.priority-table__age { white-space: nowrap; font-family: var(--ct-font-serif); }
.priority-stage { background: var(--ct-raised); border-radius: var(--ct-radius-sm); font-family: var(--ct-font-serif); font-size: var(--ct-text-sm); line-height: var(--ct-line-xs); padding: var(--ct-space-1) 6px; }
.priority-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: var(--ct-space-2); }
.priority-card { min-width: 0; background: var(--ct-inset); border-radius: var(--ct-radius-md); padding: var(--ct-space-3); }
.priority-card__header { display: flex; justify-content: space-between; align-items: center; gap: var(--ct-space-2); font-size: var(--ct-text-body); }
.priority-card__header strong { overflow-wrap: anywhere; }
.priority-card p { margin: var(--ct-space-2) 0; color: var(--ct-secondary); font-size: var(--ct-text-md); }
.priority-card__meta { display: flex; justify-content: space-between; gap: var(--ct-space-2); color: var(--ct-muted); font-family: var(--ct-font-serif); font-size: var(--ct-text-sm); }
.priority-empty { margin: 0; color: var(--ct-secondary); font-size: var(--ct-text-md); line-height: var(--ct-line-body); }
.visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip-path: inset(50%); white-space: nowrap; }
</style>
