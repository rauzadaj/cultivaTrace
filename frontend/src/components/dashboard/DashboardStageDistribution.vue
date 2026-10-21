<template>
  <dashboard-panel title="Répartition du cycle biologique" icon-src="/design/executive/1-2502/f0a6d.svg" :meta="`${formatNumber(total)} ${total === 1 ? 'plant' : 'plants'} répartis`">
    <div v-if="total" class="cycle-track" aria-hidden="true">
      <span v-for="stage in stages.filter(item => item.count > 0)" :key="stage.id" :style="{ flexGrow: stage.count, background: `var(--ct-stage-${stage.id})` }" />
    </div>
    <p v-else class="cycle-empty">Aucun plant à répartir.</p>
    <dl class="cycle-legend">
      <div v-for="stage in stages" :key="stage.id" class="cycle-legend__item" :data-stage="stage.id">
        <dt><span class="cycle-legend__dot" :style="{ background: `var(--ct-stage-${stage.id})` }" />{{ stage.label }}</dt>
        <dd class="cycle-legend__count">{{ formatNumber(stage.count) }} pl.</dd>
        <dd class="cycle-legend__share">{{ formatPercent(stage.share) }} % des stades affichés</dd>
      </div>
    </dl>
  </dashboard-panel>
</template>

<script setup lang="ts">
import { computed, type PropType } from 'vue'
import { PLANT_STAGE_LABELS, type DashboardOverviewResponse, type PlantStage } from '@/types/api'
import DashboardPanel from './DashboardPanel.vue'

const props = defineProps({
  counts: { type: Object as PropType<DashboardOverviewResponse['plants']['byStage']>, required: true },
})
const stageOrder: PlantStage[] = ['germination', 'vegetation', 'flowering', 'harvest', 'archived']
const visibleStages = computed(() => stageOrder.filter(stage => stage !== 'archived' || (props.counts.archived ?? 0) > 0))
const total = computed(() => visibleStages.value.reduce((sum, stage) => sum + (props.counts[stage] ?? 0), 0))
const stages = computed(() => visibleStages.value.map(id => ({
  id,
  label: PLANT_STAGE_LABELS[id],
  count: props.counts[id] ?? 0,
  share: total.value ? ((props.counts[id] ?? 0) / total.value) * 100 : 0,
})))
const formatNumber = (value: number) => value.toLocaleString('fr-FR')
const formatPercent = (value: number) => value.toLocaleString('fr-FR', { maximumFractionDigits: 1 })
</script>

<style scoped>
.cycle-track { display: flex; gap: 2px; height: 12px; padding: 2px; overflow: hidden; border-radius: 12px; background: var(--ct-nav); }
.cycle-track span { flex-basis: 0; border-radius: var(--ct-radius-sm); }
.cycle-legend { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); gap: var(--ct-space-2); margin: var(--ct-space-3) 0 0; }
.cycle-legend__item { min-width: 0; padding: var(--ct-space-2); background: var(--ct-inset); border-radius: var(--ct-radius-md); font-family: var(--ct-font-serif); }
.cycle-legend dt { display: flex; align-items: center; gap: 6px; color: var(--ct-secondary); font-size: var(--ct-text-sm); line-height: var(--ct-line-xs); }
.cycle-legend__dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.cycle-legend dd { margin: 0; }
.cycle-legend .cycle-legend__count { padding-top: var(--ct-space-1); font-size: var(--ct-text-body); font-weight: 700; line-height: var(--ct-line-body); }
.cycle-legend__share { color: var(--ct-muted); font-size: var(--ct-text-xs); line-height: var(--ct-line-xs); }
.cycle-empty { margin: 0; color: var(--ct-secondary); font-size: var(--ct-text-md); }
</style>
