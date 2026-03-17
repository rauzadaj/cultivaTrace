<template>
  <q-input
    outlined
    stack-label
    class="c-input"
    v-bind="$attrs"
  >
    <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
      <slot :name="slotName" v-bind="slotProps" />
    </template>
  </q-input>
</template>

<script setup lang="ts">
defineOptions({
  inheritAttrs: false,
})
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
