<template>
  <v-card flat class="surface-card comments-card">
    <div class="section-header section-header--compact">
      <div>
        <p class="section-header__eyebrow">Comments</p>
        <h2>Field notes</h2>
      </div>
    </div>

    <div v-if="!entries.length" class="empty-state empty-state--compact">
      Aucun signal recent.
    </div>
    <div v-else class="stream-list">
      <article v-for="entry in entries" :key="entry.id" class="stream-item">
        <div class="stream-item__icon">
          <v-icon :icon="entryIcon(entry.type)" size="18" />
        </div>
        <div>
          <strong>{{ entryLabel(entry.type) }}</strong>
          <p>{{ entry.crop.displayName }}</p>
          <small>{{ entry.notes ?? 'Signal capture sans note.' }}</small>
        </div>
      </article>
    </div>
  </v-card>
</template>

<script setup lang="ts">
import type { JournalEntryDto } from '../../types/api'

defineProps<{
  entries: JournalEntryDto[]
}>()

function entryLabel(type: JournalEntryDto['type']) {
  return {
    irrigation: 'Arrosage',
    fertilization: 'Fertilisation',
    environment_check: 'Controle environnement',
    stage_transition: 'Transition de stade',
    observation: 'Observation',
  }[type]
}

function entryIcon(type: JournalEntryDto['type']) {
  return {
    irrigation: 'mdi-water-outline',
    fertilization: 'mdi-flask-outline',
    environment_check: 'mdi-thermometer-lines',
    stage_transition: 'mdi-swap-horizontal',
    observation: 'mdi-eye-outline',
  }[type]
}
</script>

<style scoped>
.surface-card {
  border: 1px solid #dbe4ea;
  border-radius: 10px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(23, 35, 45, 0.06);
}

.comments-card {
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

.stream-list {
  display: grid;
  gap: 12px;
}

.stream-item {
  display: grid;
  grid-template-columns: auto minmax(0, 1fr);
  gap: 12px;
  align-items: start;
  padding: 12px;
  border: 1px solid #e4e8ec;
  border-radius: 8px;
  background: #fafbfc;
}

.stream-item__icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
  background: #eceff1;
  color: #546e7a;
}

.stream-item strong {
  display: block;
  font-size: 0.96rem;
  font-weight: 600;
}

.stream-item p,
.stream-item small {
  margin: 0;
  color: #78909c;
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
</style>
