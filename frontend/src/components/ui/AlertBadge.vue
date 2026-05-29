<template>
  <div class="alert-badge" :class="`alert-badge--${severity}`">
    <q-icon :name="icon" size="14px" />
    <span><slot /></span>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { AlertSeverity } from '../../types/api'

// Accepts the dashboard's 'default' tone in addition to the core AlertSeverity
// values, so neutral informational alerts render without a type cast.
const props = defineProps<{
  severity: AlertSeverity | 'default'
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
  gap: 6px;
  min-height: 26px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.03em;
  text-transform: uppercase;
}

.alert-badge--warning {
  color: #92400E;
  background: #FEF3C7;
  border: 1px solid #FDE68A;
}

.alert-badge--critical {
  color: #991B1B;
  background: #FEE2E2;
  border: 1px solid #FECACA;
  animation: pulse 2s ease-in-out infinite;
}

.alert-badge--healthy {
  color: #065F46;
  background: #D1FAE5;
  border: 1px solid #A7F3D0;
}

.alert-badge--default {
  color: #374151;
  background: #F3F4F6;
  border: 1px solid #E5E7EB;
}

@keyframes pulse {
  0%, 100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.2); }
  50%       { box-shadow: 0 0 0 5px rgba(220, 38, 38, 0); }
}
</style>
