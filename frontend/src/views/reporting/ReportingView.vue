<template>
  <div class="reporting-view">
    <header class="reporting-view__header">
      <div>
        <p class="eyebrow">Reporting</p>
        <h1>Exports opérationnels</h1>
        <p>Générez des rapports tenant-wide et téléchargez l’historique des exports.</p>
      </div>
    </header>

    <section class="reporting-grid">
      <q-card class="reporting-card">
        <q-card-section>
          <p class="eyebrow">Harvest summary</p>
          <h2>Récoltes par période</h2>
        </q-card-section>
        <q-card-section class="reporting-card__controls">
          <q-input v-model="harvestFilters.dateFrom" type="date" label="Du" outlined />
          <q-input v-model="harvestFilters.dateTo" type="date" label="Au" outlined />
          <q-btn color="primary" no-caps :loading="harvestLoading" label="Générer le PDF" @click="generateHarvestSummary" />
        </q-card-section>
      </q-card>

      <q-card class="reporting-card">
        <q-card-section>
          <p class="eyebrow">Audit export</p>
          <h2>Journal tenant</h2>
        </q-card-section>
        <q-card-section class="reporting-card__controls">
          <q-input v-model="auditFilters.dateFrom" type="date" label="Du" outlined />
          <q-input v-model="auditFilters.dateTo" type="date" label="Au" outlined />
          <q-select v-model="auditFilters.format" :options="formatOptions" emit-value map-options label="Format" outlined />
          <q-btn color="primary" no-caps :loading="auditLoading" label="Générer l’export" @click="generateAuditExport" />
        </q-card-section>
      </q-card>
    </section>

    <q-card class="reporting-card">
      <q-card-section class="reporting-history__head">
        <div>
          <p class="eyebrow">Historique</p>
          <h2>Exports récents</h2>
        </div>
        <q-btn flat color="primary" no-caps label="Rafraîchir" :loading="loading" @click="loadHistory" />
      </q-card-section>

      <q-card-section>
        <div v-if="exports.length" class="reporting-history">
          <article v-for="item in exports" :key="item.id" class="reporting-history__item">
            <div>
              <strong>{{ reportTypeLabel(item.type) }}</strong>
              <p>{{ item.fileName }}</p>
              <small>{{ formatDateTime(item.createdAt) }}</small>
            </div>
            <div class="reporting-history__meta">
              <q-badge color="primary" outline>{{ item.format.toUpperCase() }}</q-badge>
              <q-btn flat no-caps color="primary" label="Télécharger" @click="downloadExport(item)" />
            </div>
          </article>
        </div>
        <div v-else class="reporting-history__empty">
          <q-icon name="mdi-file-chart-outline" size="28px" />
          <strong>Aucun export généré</strong>
        </div>
      </q-card-section>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { reportingApi } from '@/services/api'
import type { HydraCollection, ReportExport } from '@/types/api'

const $q = useQuasar()
const loading = ref(false)
const harvestLoading = ref(false)
const auditLoading = ref(false)
const exports = ref<ReportExport[]>([])

const today = new Date().toISOString().slice(0, 10)
const monthStart = `${today.slice(0, 8)}01`

const harvestFilters = ref({
  dateFrom: monthStart,
  dateTo: today,
})

const auditFilters = ref<{
  dateFrom: string
  dateTo: string
  format: 'pdf' | 'csv'
}>({
  dateFrom: monthStart,
  dateTo: today,
  format: 'csv',
})

const formatOptions = [
  { label: 'CSV', value: 'csv' },
  { label: 'PDF', value: 'pdf' },
] as const

function collectionMembers<T>(collection: HydraCollection<T>): T[] {
  return collection['hydra:member'] ?? collection.member ?? []
}

async function loadHistory() {
  loading.value = true
  try {
    const { data } = await reportingApi.list()
    exports.value = collectionMembers(data)
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Impossible de charger l’historique.' })
  } finally {
    loading.value = false
  }
}

async function generateHarvestSummary() {
  harvestLoading.value = true
  try {
    const { data } = await reportingApi.createHarvestSummary(harvestFilters.value)
    $q.notify({ type: 'positive', message: 'Harvest summary généré.' })
    await loadHistory()
    await downloadExport(data)
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Export impossible.' })
  } finally {
    harvestLoading.value = false
  }
}

async function generateAuditExport() {
  auditLoading.value = true
  try {
    const { data } = await reportingApi.createAuditExport(auditFilters.value)
    $q.notify({ type: 'positive', message: 'Audit export généré.' })
    await loadHistory()
    await downloadExport(data)
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Export impossible.' })
  } finally {
    auditLoading.value = false
  }
}

async function downloadExport(item: ReportExport) {
  await reportingApi.download(`/api/reporting/exports/${item.id}/download`, item.fileName)
}

function reportTypeLabel(type: ReportExport['type']): string {
  return type === 'harvest_summary' ? 'Harvest summary' : 'Audit export'
}

function formatDateTime(value: string): string {
  return new Date(value).toLocaleString('fr-FR')
}

onMounted(async () => {
  await loadHistory()
})
</script>

<style scoped lang="scss">
.reporting-view { display: grid; gap: 20px; }
.reporting-view__header { display: grid; gap: 6px; }
.reporting-view h1, .reporting-view h2, .reporting-view p { margin: 0; }
.eyebrow {
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}
.reporting-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
}
.reporting-card__controls {
  display: grid;
  gap: 12px;
}
.reporting-history__head {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: center;
}
.reporting-history {
  display: grid;
  gap: 12px;
}
.reporting-history__item {
  display: flex;
  justify-content: space-between;
  gap: 16px;
  align-items: center;
  padding: 14px;
  border: 1px solid #e2e8f0;
  border-radius: 14px;
  background: #f8fafc;
}
.reporting-history__item p,
.reporting-history__item small { display: block; margin-top: 4px; color: #4a5568; }
.reporting-history__meta {
  display: flex;
  gap: 12px;
  align-items: center;
}
.reporting-history__empty {
  display: grid;
  gap: 8px;
  justify-items: start;
  padding: 8px 0;
}
</style>
