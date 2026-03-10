<template>
  <div class="quick-actions">
    <v-btn
      v-for="action in actions"
      :key="action.type"
      class="quick-action"
      rounded="pill"
      :color="action.color"
      :prepend-icon="action.icon"
      :loading="pendingType === action.type"
      :disabled="disabled"
      @click="trigger(action.type, action.label)"
    >
      {{ action.label }}
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

const actions: Array<{ type: JournalEntryDto['type']; label: string; color: string; icon: string }> = [
  { type: 'irrigation', label: 'Arrosage', color: 'primary', icon: 'mdi-water-outline' },
  { type: 'fertilization', label: 'Nutrition', color: 'secondary', icon: 'mdi-flask-outline' },
  { type: 'environment_check', label: 'Contrôle climat', color: 'accent', icon: 'mdi-thermometer-lines' },
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
  gap: 10px;
}

.quick-action {
  min-width: 0;
}
</style>
