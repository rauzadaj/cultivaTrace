<template>
  <c-card class="dashboard-panel">
    <header class="dashboard-panel__header">
      <h2>
        <img v-if="iconSrc" :src="iconSrc" alt="" class="dashboard-panel__icon" />
        <q-icon v-else-if="icon" :name="icon" size="18px" class="dashboard-panel__icon" />
        {{ title }}
      </h2>
      <span v-if="meta" class="dashboard-panel__meta">{{ meta }}</span>
      <slot name="action" />
    </header>
    <slot />
  </c-card>
</template>

<script setup lang="ts">
import CCard from '@/components/ui/CCard.vue'

defineProps({
  title: { type: String, required: true },
  icon: { type: String, default: '' },
  iconSrc: { type: String, default: '' },
  meta: { type: String, default: '' },
})
</script>

<style scoped>
.dashboard-panel.c-card {
  min-width: 0;
  padding: var(--ct-space-4);
  background: var(--ct-surface);
  color: var(--ct-text);
  border-radius: var(--ct-radius-lg);
  box-shadow: 0 1px 2px rgb(0 0 0 / 5%);
}
.dashboard-panel__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--ct-space-3);
  margin-bottom: var(--ct-space-3);
}
.dashboard-panel__header h2 {
  display: flex;
  align-items: center;
  gap: var(--ct-space-2);
  margin: 0;
  font-size: var(--ct-text-xl);
  font-weight: 600;
  line-height: var(--ct-line-lg);
  letter-spacing: -0.08px;
}
.dashboard-panel__icon { width: 18px; height: 18px; flex-shrink: 0; color: var(--ct-mint); }
.dashboard-panel__meta {
  color: var(--ct-muted);
  font-family: var(--ct-font-serif);
  font-size: var(--ct-text-sm);
  line-height: var(--ct-line-xs);
  text-align: right;
}
@media (max-width: 479px) {
  .dashboard-panel__header { align-items: flex-start; flex-wrap: wrap; }
}
</style>
