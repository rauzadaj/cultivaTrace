<template>
  <span class="stage-chip" :class="`stage-chip--${normalizedStage}`">
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { LifecycleStage } from '../../types/api'

const props = defineProps<{
  stage: LifecycleStage
}>()

const normalizedStage = computed(() => {
  if (props.stage === 'germination' || props.stage === 'seedling') return 'germination'
  if (props.stage === 'vegetation' || props.stage === 'veg') return 'vegetation'
  if (props.stage === 'flowering' || props.stage === 'flower') return 'flowering'

  return props.stage
})

const label = computed(() => {
  return {
    germination: 'Germination',
    vegetation: 'Vegetation',
    flowering: 'Flowering',
    harvest: 'Harvest',
    archived: 'Archived',
  }[normalizedStage.value] ?? normalizedStage.value
})
</script>

<style scoped>
.stage-chip {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 4px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  color: #fff;
}

.stage-chip--germination {
  background: #81c784;
}

.stage-chip--vegetation {
  background: #4caf50;
}

.stage-chip--flowering {
  background: #ff9800;
}

.stage-chip--harvest {
  background: #f44336;
}

.stage-chip--archived {
  background: #9e9e9e;
}
</style>
