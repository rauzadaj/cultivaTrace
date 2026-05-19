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
  border-radius: 14px;
  padding: 20px;
  background: #FFFFFF;
  border: 1px solid #E5E7EB;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04), 0 1px 8px rgba(0, 0, 0, 0.02);
}
</style>
