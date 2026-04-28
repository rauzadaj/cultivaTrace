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
  min-height: 48px;
  border-radius: 8px;
  font-weight: 600;
  letter-spacing: 0;
}

.c-btn--primary {
  background: #1b6b3a;
  color: #fff;
}

.c-btn--secondary {
  background: transparent;
  border-color: #1b6b3a;
  color: #1b6b3a;
}

.c-btn--danger {
  background: #c53030;
  color: #fff;
}

.c-btn--ghost {
  background: transparent;
}
</style>
