<template>
  <span class="stage-chip" :class="`stage-chip--${normalizedStage}`">
    {{ label }}
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { PlantStage } from '../../types/api'

const props = defineProps<{
  stage: PlantStage
}>()

const normalizedStage = computed(() => props.stage)

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
  min-height: 22px;
  padding: 2px 10px;
  border-radius: 999px;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.02em;
}

.stage-chip--germination {
  background: #DCFCE7;
  color: #15803D;
}

.stage-chip--vegetation {
  background: #BBF7D0;
  color: #166534;
}

.stage-chip--flowering {
  background: #FFEDD5;
  color: #C2410C;
}

.stage-chip--harvest {
  background: #FEE2E2;
  color: #991B1B;
}

.stage-chip--archived {
  background: #F3F4F6;
  color: #6B7280;
}
</style>
