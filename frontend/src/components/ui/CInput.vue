<template>
  <component
    :is="componentName"
    outlined
    stack-label
    class="c-input"
    v-bind="$attrs"
  >
    <template v-if="$slots.prepend" #prepend>
      <slot name="prepend" />
    </template>
    <template v-if="$slots.append" #append>
      <slot name="append" />
    </template>
    <template v-if="$slots.before" #before>
      <slot name="before" />
    </template>
    <template v-if="$slots.after" #after>
      <slot name="after" />
    </template>
    <template v-if="$slots.hint" #hint>
      <slot name="hint" />
    </template>
    <template v-if="$slots.error" #error>
      <slot name="error" />
    </template>
    <template v-if="$slots.default" #default>
      <slot />
    </template>
  </component>
</template>

<script setup lang="ts">
import { computed } from 'vue'

defineOptions({
  inheritAttrs: false,
})

const props = withDefaults(defineProps<{
  select?: boolean
}>(), {
  select: false,
})

const componentName = computed(() => props.select ? 'q-select' : 'q-input')
</script>

<style scoped>
.c-input {
  --q-primary: #16A34A;
}

.c-input :deep(.q-field__control) {
  min-height: 52px;
  border-radius: 10px;
  background: #FFFFFF;
}

.c-input :deep(.q-field__label) {
  font-size: 0.875rem;
  color: #6B7280;
  font-weight: 500;
}

.c-input :deep(.q-field__native),
.c-input :deep(.q-field__input) {
  color: #111827;
  font-family: 'Inter', sans-serif;
}

.c-input :deep(.q-field--outlined .q-field__control:before) {
  border-color: #E5E7EB;
  transition: border-color 0.15s;
}

.c-input :deep(.q-field--focused .q-field__control:before),
.c-input :deep(.q-field--focused .q-field__control:after) {
  border-color: #16A34A;
}

.c-input :deep(.q-field--error .q-field__control:before),
.c-input :deep(.q-field--error .q-field__control:after) {
  border-color: #DC2626;
}

.c-input :deep(.q-field__marginal) {
  color: #9CA3AF;
}
</style>
