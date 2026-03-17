<template>
  <q-card flat class="surface-card analytics-card">
    <div class="section-header section-header--compact">
      <div>
        <p class="section-header__eyebrow">Analytics</p>
        <h2>Genetic performance</h2>
      </div>
    </div>

    <div v-if="!items.length" class="empty-state empty-state--compact">
      Aucune moyenne calculee.
    </div>
    <div v-else class="performance-list">
      <article v-for="item in items" :key="item.geneticId" class="performance-row">
        <div>
          <strong>{{ item.geneticCode }}</strong>
          <p>{{ item.geneticName }}</p>
        </div>
        <div class="performance-row__bar">
          <div class="performance-row__track">
            <div class="performance-row__fill" :style="{ width: `${analyticsWidth(item.averageCycleDays)}%` }" />
          </div>
          <small>{{ item.completedCycles }} cycles · {{ item.averageCycleDays.toFixed(1) }} jours</small>
        </div>
      </article>
    </div>
  </q-card>
</template>

<script setup lang="ts">
import type { GeneticCycleAverageDto } from '../../types/api'

defineProps<{
  items: GeneticCycleAverageDto[]
  analyticsWidth: (value: number) => number
}>()
</script>

<style scoped>
.surface-card {
  border: 1px solid #dbe4ea;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(23, 35, 45, 0.06);
}

.analytics-card {
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

.performance-list {
  display: grid;
  gap: 12px;
}

.performance-row {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(0, 1.2fr);
  gap: 12px;
  align-items: start;
  padding: 12px;
  border: 1px solid #e4e8ec;
  border-radius: 8px;
  background: #fafbfc;
}

.performance-row strong {
  display: block;
  font-size: 0.96rem;
  font-weight: 600;
}

.performance-row p,
.performance-row small {
  margin: 0;
  color: #78909c;
}

.performance-row__bar {
  display: grid;
  gap: 8px;
}

.performance-row__track {
  height: 8px;
  border-radius: 999px;
  background: #e4ebf0;
  overflow: hidden;
}

.performance-row__fill {
  height: 100%;
  background: linear-gradient(90deg, #00acc1, #26c6da);
}

.empty-state {
  padding: 18px;
  border: 1px dashed #ccd6dd;
  border-radius: 8px;
  background: #fafcfd;
  color: #78909c;
  text-align: center;
}

.empty-state--compact {
  padding: 14px;
}

@media (max-width: 640px) {
  .performance-row {
    grid-template-columns: 1fr;
  }
}
</style>
