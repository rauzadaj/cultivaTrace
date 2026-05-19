<template>
  <q-btn
    no-caps
    ripple
    :flat="isGhost"
    :outline="isSecondary"
    :unelevated="isFilled"
    :color="color"
    class="c-btn"
    :class="`c-btn--${variant}`"
    v-bind="$attrs"
  >
    <slot />
  </q-btn>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { CBtnVariant } from '../../types/api'

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(defineProps<{
  variant?: CBtnVariant
}>(), {
  variant: 'primary',
})

const isGhost = computed(() => props.variant === 'ghost')
const isSecondary = computed(() => props.variant === 'secondary')
const isFilled = computed(() => props.variant === 'primary' || props.variant === 'danger')
const color = computed(() => {
  if (props.variant === 'danger') return 'negative'
  if (props.variant === 'ghost') return 'primary'

  return 'primary'
})
</script>

<style scoped>
.c-btn {
  min-height: 44px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 0.875rem;
  letter-spacing: -0.01em;
}

.c-btn--primary {
  background: #16A34A;
  color: #FFFFFF;
  box-shadow: 0 1px 3px rgba(22, 163, 74, 0.3);
}

.c-btn--secondary {
  background: transparent;
  border-color: #E5E7EB;
  color: #374151;
}

.c-btn--danger {
  background: #DC2626;
  color: #FFFFFF;
}

.c-btn--ghost {
  background: transparent;
  color: #6B7280;
}
</style>
