<template>
  <div class="compliance-view">
    <header>
      <p class="eyebrow">Conformite</p>
      <h1>Exports reglmentaires</h1>
    </header>

    <q-card class="compliance-card">
      <q-card-section>
        <p class="eyebrow">CTS</p>
        <h2>Rapport mensuel</h2>
        <p>Genere le CSV de conformite pour le mois selectionne.</p>
      </q-card-section>
      <q-card-section class="compliance-card__controls">
        <q-input v-model="month" :type="monthInputType" label="Mois" outlined />
        <q-btn color="primary" no-caps label="Telecharger le CSV" :loading="loading" @click="download" />
      </q-card-section>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useQuasar } from 'quasar'
import { complianceApi } from '@/services/api'

const $q = useQuasar()
const month = ref(new Date().toISOString().slice(0, 7))
const loading = ref(false)
// Native month picker: Quasar's QInput type prop does not enumerate "month",
// so widen it through a typed constant rather than casting inline in template.
const monthInputType = 'month' as unknown as 'date'

async function download() {
  loading.value = true
  try {
    await complianceApi.ctsReport(month.value)
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Export impossible.' })
  } finally {
    loading.value = false
  }
}
</script>

<style scoped lang="scss">
.compliance-view { display: grid; gap: 16px; }
.compliance-view h1 { margin: 0; font-size: 1.75rem; }
.eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}
.compliance-card__controls {
  display: grid;
  gap: 12px;
  grid-template-columns: minmax(180px, 240px) auto;
  align-items: end;
}
</style>
