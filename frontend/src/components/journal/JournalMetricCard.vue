<template>
  <article class="journal-metric">
    <div class="journal-metric__top">
      <span>{{ label }}</span>
      <span class="journal-metric__icon" :class="`journal-metric__icon--${tone}`">
        <img v-if="iconSrc" :src="iconSrc" alt="" aria-hidden="true" class="journal-metric__asset">
        <q-icon v-else :name="icon" size="16px" aria-hidden="true" />
      </span>
    </div>
    <div class="journal-metric__value">{{ value }}</div>
    <div class="journal-metric__bottom">
      <span>{{ detail }}</span>
      <span class="journal-metric__dot" :class="`journal-metric__dot--${tone}`" aria-hidden="true" />
    </div>
  </article>
</template>

<script setup lang="ts">
withDefaults(defineProps<{
  label: string
  value: string | number
  detail: string
  icon: string
  iconSrc?: string
  tone?: 'mint' | 'blue' | 'neutral' | 'warning' | 'asset'
}>(), { tone: 'mint', iconSrc: '' })
</script>

<style scoped>
.journal-metric { display: flex; min-width: 0; min-height: 145px; flex-direction: column; justify-content: space-between; padding: 17px; border: 1px solid color-mix(in srgb, var(--ct-border-strong) 55%, transparent); border-radius: var(--ct-radius-md); background: var(--ct-inset); }
.journal-metric__top { display: flex; min-height: 34px; align-items: center; justify-content: space-between; gap: var(--ct-space-2); color: var(--ct-muted); font-size: 11px; font-weight: var(--ct-weight-medium); line-height: 14px; letter-spacing: .55px; text-transform: uppercase; }
.journal-metric__icon { display: grid; width: 26px; height: 34px; flex: 0 0 auto; place-items: center; border-radius: var(--ct-radius-md); background: color-mix(in srgb, var(--ct-mint) 8%, transparent); color: var(--ct-mint); }
.journal-metric__icon--blue { background: var(--ct-info-soft); color: var(--ct-info); }
.journal-metric__icon--neutral { background: var(--ct-neutral-soft); color: var(--ct-neutral); }
.journal-metric__icon--warning { background: var(--ct-warning-soft); color: var(--ct-warning); }
.journal-metric__icon--asset { width: auto; background: transparent; }
.journal-metric__asset { display: block; flex: 0 0 auto; }
.journal-metric__value { overflow-wrap: anywhere; color: var(--ct-text); font-size: 32px; font-weight: var(--ct-weight-bold); line-height: 40px; letter-spacing: -.8px; }
.journal-metric__bottom { display: flex; min-height: 25px; align-items: center; justify-content: space-between; gap: var(--ct-space-2); padding-top: 9px; border-top: 1px solid color-mix(in srgb, var(--ct-border-strong) 32%, transparent); color: var(--ct-muted); font: 12px/16px var(--ct-font-serif); }
.journal-metric__dot { width: 8px; height: 8px; flex: 0 0 auto; border-radius: 50%; background: var(--ct-mint); }
.journal-metric__dot--blue { background: var(--ct-info); }
.journal-metric__dot--neutral { background: var(--ct-neutral); }
.journal-metric__dot--warning { background: var(--ct-warning); }
@media (max-width: 599px) { .journal-metric { min-height: 128px; padding: var(--ct-space-3); } .journal-metric__value { font-size: 26px; line-height: 32px; } .journal-metric__top { font-size: 10px; } }
</style>
