<template>
  <v-card flat class="surface-card telemetry-card">
    <div class="section-header section-header--compact">
      <div>
        <p class="section-header__eyebrow">Telemetry</p>
        <h2>Realtime metrics</h2>
      </div>
    </div>

    <div class="telemetry-grid">
      <div class="telemetry-card__item">
        <strong>{{ journalCount }}</strong>
        <span>events</span>
      </div>
      <div class="telemetry-card__item">
        <strong>{{ averageYield ?? '—' }}</strong>
        <span>yield g</span>
      </div>
      <div class="telemetry-card__item">
        <strong>{{ averagePhLabel }}</strong>
        <span>average pH</span>
      </div>
      <div class="telemetry-card__item">
        <strong>{{ activeRatioLabel }}</strong>
        <span>active lots</span>
      </div>
    </div>
  </v-card>
</template>

<script setup lang="ts">
defineProps<{
  journalCount: number
  averageYield: number | null
  averagePhLabel: string
  activeRatioLabel: string
}>()
</script>

<style scoped>
.surface-card {
  border: 1px solid #dbe4ea;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(23, 35, 45, 0.06);
}

.telemetry-card {
  padding: 18px;
}

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.section-header--compact {
  margin-bottom: 14px;
}

.section-header__eyebrow {
  margin: 0 0 4px;
  color: #90a4ae;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.section-header h2 {
  margin: 0;
  color: #37474f;
  font-size: 1.2rem;
  font-weight: 500;
}

.telemetry-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 12px;
}

.telemetry-card__item {
  padding: 14px;
  border: 1px solid #e4e8ec;
  border-radius: 8px;
  background: #fafbfc;
  text-align: center;
}

.telemetry-card__item strong {
  display: block;
  color: #455a64;
  font-size: 1.35rem;
}

.telemetry-card__item span {
  color: #78909c;
  font-size: 0.84rem;
}

@media (max-width: 640px) {
  .telemetry-grid {
    grid-template-columns: 1fr;
  }
}
</style>
