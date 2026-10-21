<template>
  <div class="shell-brand" :class="{ 'shell-brand--compact': compact }">
    <div class="shell-brand__identity">
      <span class="shell-brand__mark" aria-hidden="true">
        <img src="/design/executive/1-3052/839b1.svg" alt="">
      </span>
      <div v-if="!compact" class="shell-brand__copy">
        <strong>CultivaTrace</strong>
        <span>Precision OS</span>
      </div>
    </div>
    <span v-if="plan && !compact" class="shell-brand__plan" :title="`Abonnement ${plan}`">{{ plan }}</span>
  </div>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  compact?: boolean
  plan?: string
}>(), {
  compact: false,
  plan: '',
})
</script>

<style scoped>
.shell-brand {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--ct-space-2);
  height: calc(var(--ct-header-height) + env(safe-area-inset-top));
  flex: 0 0 auto;
  padding: env(safe-area-inset-top) var(--ct-space-4) 0;
  border-bottom: 1px solid var(--ct-border);
}
.shell-brand__identity { display: flex; align-items: center; gap: var(--ct-space-2); min-width: 0; }
.shell-brand__mark {
  display: grid;
  place-items: center;
  width: 28px;
  height: 28px;
  flex: 0 0 auto;
  background: var(--ct-raised);
  border: 1px solid color-mix(in srgb, var(--ct-mint) 30%, transparent);
  border-radius: var(--ct-radius-md);
}
.shell-brand__mark img { width: 15px; height: 15px; }
.shell-brand__copy { display: flex; flex-direction: column; }
.shell-brand__copy strong { color: var(--ct-text); font: 600 16px/24px var(--ct-font-sans); letter-spacing: -0.4px; }
.shell-brand__copy span { color: var(--ct-muted); font: 11px/14px var(--ct-font-serif); letter-spacing: 0.55px; text-transform: uppercase; }
.shell-brand__plan {
  max-width: 66px;
  overflow: hidden;
  text-overflow: ellipsis;
  padding: 3px 7px;
  border: 1px solid color-mix(in srgb, var(--ct-mint) 30%, transparent);
  border-radius: var(--ct-radius-md);
  background: color-mix(in srgb, var(--ct-primary) 20%, transparent);
  color: var(--ct-mint);
  font: 700 10px/15px var(--ct-font-serif);
  letter-spacing: 0.25px;
  text-transform: uppercase;
}
.shell-brand--compact { justify-content: center; padding-inline: var(--ct-space-2); }
</style>
