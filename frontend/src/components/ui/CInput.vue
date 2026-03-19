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
  --q-primary: #1b6b3a;
}

.c-input :deep(.q-field__control) {
  min-height: 52px;
  border-radius: 12px;
}

.c-input :deep(.q-field__label) {
  font-size: 0.875rem;
}

.c-input :deep(.q-field--outlined .q-field__control:before) {
  border-color: #e2e8f0;
}

.c-input :deep(.q-field--focused .q-field__control:before),
.c-input :deep(.q-field--focused .q-field__control:after) {
  border-color: #1b6b3a;
}

.c-input :deep(.q-field--error .q-field__control:before),
.c-input :deep(.q-field--error .q-field__control:after) {
  border-color: #c53030;
}
</style>
