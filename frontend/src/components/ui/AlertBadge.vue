<template>
  <div class="alert-badge" :class="`alert-badge--${severity}`">
    <q-icon :name="icon" size="18px" />
    <span><slot /></span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { AlertSeverity } from '../../types/api'

const props = defineProps<{
  severity: AlertSeverity
}>()

const icon = computed(() => {
  if (props.severity === 'critical') return 'mdi-alert-circle'
  if (props.severity === 'healthy') return 'mdi-check-circle'

  return 'mdi-alert'
})
</script>

<style scoped>
.alert-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  min-height: 32px;
  padding: 6px 10px;
  border-radius: 999px;
  font-size: 0.75rem;
  font-weight: 600;
}

.alert-badge--warning {
  color: #8a5a08;
  background: rgba(245, 158, 11, 0.14);
}

.alert-badge--critical {
  color: #c53030;
  background: rgba(197, 48, 48, 0.12);
  animation: pulse 1.6s ease-in-out infinite;
}

.alert-badge--healthy {
  color: #1b6b3a;
  background: rgba(27, 107, 58, 0.12);
}

@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(197, 48, 48, 0.18); }
  50% { box-shadow: 0 0 0 8px rgba(197, 48, 48, 0); }
}
</style>
