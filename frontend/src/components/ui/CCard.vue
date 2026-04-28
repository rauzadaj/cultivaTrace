<template>
  <q-card flat class="c-card" v-bind="$attrs">
    <slot />
  </q-card>
</template>

<script setup lang="ts">
defineOptions({
  inheritAttrs: false,
})
</script>

<style scoped>
.c-card {
  border-radius: 12px;
  padding: 16px;
  background: #fff;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
</style>
