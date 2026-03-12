<template>
  <div class="quick-actions">
    <v-btn
      v-for="action in actions"
      :key="action.type"
      class="quick-action"
      :class="`quick-action--${action.type}`"
      rounded="xl"
      variant="flat"
      :loading="pendingType === action.type"
      :disabled="disabled"
      @click="trigger(action.type, action.label)"
    >
      <span class="quick-action__content">
        <span class="quick-action__icon">
          <v-icon :icon="action.icon" size="18" />
        </span>
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

const actions: Array<{ type: JournalEntryDto['type']; label: string; hint: string; icon: string }> = [
  { type: 'irrigation', label: 'Arrosage', hint: 'pulse hydrique', icon: 'mdi-water-outline' },
  { type: 'fertilization', label: 'Nutrition', hint: 'solution mere', icon: 'mdi-flask-outline' },
  { type: 'environment_check', label: 'Climat', hint: 'controle serre', icon: 'mdi-thermometer-lines' },
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
  gap: 10px;
}

.quick-action {
  justify-content: flex-start;
  min-width: 0;
  min-height: 58px;
  text-transform: none;
  letter-spacing: 0;
}

.quick-action__content {
  display: flex;
  align-items: center;
  gap: 12px;
}

.quick-action__icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.12);
}

.quick-action__text {
  display: grid;
  text-align: left;
}

.quick-action__text strong {
  font-size: 0.95rem;
}

.quick-action__text small {
  color: rgba(241, 247, 251, 0.72);
  font-size: 0.78rem;
}

.quick-action--irrigation {
  background: linear-gradient(135deg, rgba(17, 113, 164, 0.96), rgba(24, 145, 193, 0.86));
}

.quick-action--fertilization {
  background: linear-gradient(135deg, rgba(157, 95, 24, 0.94), rgba(214, 142, 34, 0.88));
}

.quick-action--environment_check {
  background: linear-gradient(135deg, rgba(21, 101, 76, 0.96), rgba(42, 167, 126, 0.86));
}

@media (min-width: 640px) {
  .quick-actions {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }
}
</style>
