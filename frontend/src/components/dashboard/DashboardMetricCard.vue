<template>
  <c-card class="metric-card" :class="`metric-card--${tone}`" :aria-label="`${label} : ${value}`">
    <div class="metric-card__header">
      <div>
        <h2>{{ label }}</h2>
        <div class="metric-card__value">{{ value }}</div>
      </div>
      <span class="metric-card__icon">
        <img v-if="iconSrc" :src="iconSrc" alt="" />
        <q-icon v-else :name="icon" size="18px" />
      </span>
    </div>
    <div class="metric-card__detail"><slot /></div>
    <p class="metric-card__caption">{{ caption }}</p>
  </c-card>
</template>

<script setup lang="ts">
import CCard from '@/components/ui/CCard.vue'

defineProps({
  label: { type: String, required: true },
  value: { type: String, required: true },
  caption: { type: String, required: true },
  icon: { type: String, default: 'mdi-chart-box-outline' },
  iconSrc: { type: String, default: '' },
  tone: { type: String, default: 'mint' },
})
</script>

<style scoped>
.metric-card.c-card {
  --metric-color: var(--ct-mint);
  --metric-background: var(--ct-mint-soft);
  display: flex;
  flex-direction: column;
  min-width: 0;
  min-height: 190px;
  padding: var(--ct-space-4);
  color: var(--ct-text);
  background: var(--ct-surface);
  border-radius: var(--ct-radius-lg);
  box-shadow: 0 1px 2px rgb(0 0 0 / 5%);
}
.metric-card.metric-card--info { --metric-color: var(--ct-info); --metric-background: var(--ct-info-soft); }
.metric-card.metric-card--danger { --metric-color: var(--ct-danger); --metric-background: var(--ct-danger-soft); }
.metric-card.metric-card--warning { --metric-color: var(--ct-warning); --metric-background: var(--ct-warning-soft); }
.metric-card__header { display: flex; justify-content: space-between; align-items: flex-start; gap: var(--ct-space-2); }
.metric-card h2 {
  margin: 0;
  color: var(--ct-muted);
  font-size: var(--ct-text-sm);
  font-weight: 500;
  line-height: var(--ct-line-xs);
  letter-spacing: 0.55px;
  text-transform: uppercase;
}
.metric-card__value {
  padding-top: var(--ct-space-1);
  font-family: var(--ct-font-serif);
  font-size: var(--ct-text-heading);
  font-weight: 700;
  line-height: var(--ct-line-heading);
  letter-spacing: -0.8px;
  overflow-wrap: anywhere;
}
.metric-card--danger .metric-card__value { color: var(--ct-danger); }
.metric-card__icon { display: grid; place-items: center; flex-shrink: 0; width: 36px; height: 36px; border-radius: var(--ct-radius-md); background: var(--metric-background); color: var(--metric-color); }
.metric-card__icon img { width: 18px; height: 18px; }
.metric-card__detail { display: grid; align-content: center; flex: 1; padding-block: var(--ct-space-3); color: var(--ct-secondary); font-size: var(--ct-text-md); line-height: var(--ct-line-body); }
.metric-card__caption { margin: 0; color: var(--ct-muted); font-family: var(--ct-font-serif); font-size: var(--ct-text-sm); line-height: var(--ct-line-xs); }
@media (max-width: 479px) {
  .metric-card.c-card { min-height: 176px; padding: var(--ct-space-3); }
  .metric-card__icon { width: 28px; height: 28px; }
}
</style>
