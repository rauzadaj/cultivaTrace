<template>
  <div class="quick-actions">
    <q-btn
      v-for="action in actions"
      :key="action.type"
      class="quick-action"
      :class="`quick-action--${action.type}`"
      no-caps
      rounded
      unelevated
      :loading="pendingType === action.type"
      :disabled="disabled"
      @click="trigger(action.type, action.label)"
    >
      <span class="quick-action__content">
        <q-icon :name="action.icon" size="18px" />
        <span class="quick-action__text">
          <strong>{{ action.label }}</strong>
          <small>{{ action.hint }}</small>
        </span>
      </span>
    </q-btn>
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
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.quick-action {
  flex: 1 1 132px;
  justify-content: flex-start;
  align-items: stretch;
  min-width: 0;
  min-height: 44px;
  padding: 0 12px;
  letter-spacing: 0;
  border: 1px solid #d8e2e8;
  border-radius: 12px;
  background: #f7fafc;
  box-shadow: none;
}

.quick-action__content {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 8px;
  width: 100%;
}

.quick-action__text {
  display: grid;
  text-align: left;
  line-height: 1.05;
  min-width: 0;
}

.quick-action__text strong {
  font-size: 0.82rem;
  font-weight: 600;
  color: #455a64;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.quick-action__text small {
  color: #78909c;
  font-size: 0.66rem;
  text-transform: uppercase;
  letter-spacing: 0.04em;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.quick-action--irrigation {
  background: #edf6ff;
  border-color: #d6e8f7;
  color: #2b6cb0;
}

.quick-action--fertilization {
  background: #fff5e7;
  border-color: #f0ddbd;
  color: #b7791f;
}

.quick-action--environment_check {
  background: #eef7f0;
  border-color: #d8e7db;
  color: #2f855a;
}

.quick-action :deep(.q-btn__content) {
  width: 100%;
  justify-content: flex-start;
}

.quick-action :deep(.q-icon) {
  flex: 0 0 auto;
}

@media (max-width: 1600px) {
  .quick-actions {
    flex-direction: column;
  }
}

@media (max-width: 640px) {
  .quick-action {
    justify-content: flex-start;
  }

  .quick-action__content {
    justify-content: flex-start;
  }
}
</style>
