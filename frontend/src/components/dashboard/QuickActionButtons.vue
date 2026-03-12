<template>
  <div class="quick-actions">
    <v-btn
      v-for="action in actions"
      :key="action.type"
      class="quick-action"
      :class="`quick-action--${action.type}`"
      rounded="lg"
      variant="tonal"
      :color="action.color"
      :loading="pendingType === action.type"
      :disabled="disabled"
      @click="trigger(action.type, action.label)"
    >
      <span class="quick-action__content">
        <v-icon :icon="action.icon" size="18" />
        <span class="quick-action__text">
          <strong>{{ action.label }}</strong>
          <small>{{ action.hint }}</small>
        </span>
      </span>
    </v-btn>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useCropStore } from '../../stores/useCropStore'
import type { JournalEntryDto } from '../../types/api'

const props = defineProps<{
  cropIri: string
  disabled?: boolean
}>()

const cropStore = useCropStore()
const pendingType = ref<JournalEntryDto['type'] | null>(null)

const actions: Array<{
  type: JournalEntryDto['type']
  label: string
  hint: string
  icon: string
  color: string
}> = [
  { type: 'irrigation', label: 'Arrosage', hint: 'pulse hydrique', icon: 'mdi-water-outline', color: 'info' },
  { type: 'fertilization', label: 'Nutrition', hint: 'solution mere', icon: 'mdi-flask-outline', color: 'warning' },
  { type: 'environment_check', label: 'Climat', hint: 'controle serre', icon: 'mdi-thermometer-lines', color: 'success' },
]

async function trigger(type: JournalEntryDto['type'], label: string) {
  pendingType.value = type

  try {
    await cropStore.appendQuickEntry(props.cropIri, type, `${label} saisi depuis le dashboard terrain.`)
  } finally {
    pendingType.value = null
  }
}
</script>

<style scoped>
.quick-actions {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 10px;
}

.quick-action {
  justify-content: center;
  min-width: 0;
  min-height: 52px;
  text-transform: none;
  letter-spacing: 0;
  border: 1px solid rgba(148, 163, 184, 0.18);
  box-shadow: none;
}

.quick-action__content {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
}

.quick-action__text {
  display: grid;
  text-align: left;
  line-height: 1.05;
}

.quick-action__text strong {
  font-size: 0.9rem;
  font-weight: 600;
  color: #455a64;
}

.quick-action__text small {
  color: #78909c;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
}

.quick-action--irrigation :deep(.v-btn__underlay) {
  background: rgba(3, 169, 244, 0.12);
}

.quick-action--fertilization :deep(.v-btn__underlay) {
  background: rgba(255, 152, 0, 0.12);
}

.quick-action--environment_check :deep(.v-btn__underlay) {
  background: rgba(76, 175, 80, 0.12);
}

@media (max-width: 640px) {
  .quick-actions {
    grid-template-columns: 1fr;
  }

  .quick-action {
    justify-content: flex-start;
  }

  .quick-action__content {
    justify-content: flex-start;
  }
}
</style>
