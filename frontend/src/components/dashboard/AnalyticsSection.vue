<template>
  <div>
    <div class="section-grid section-grid--metrics mb-1">
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Genetics</span>
          <strong>{{ filteredGenetics.length }}</strong>
          <small>Available lines in the catalog</small>
      </q-card>
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Analytics rows</span>
          <strong>{{ cropStore.cycleAverages.length }}</strong>
          <small>Computed mean cycle durations</small>
      </q-card>
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Completed cycles</span>
          <strong>{{ totalCompletedCycles }}</strong>
          <small>Included in aggregation</small>
      </q-card>
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Top line</span>
          <strong>{{ topGeneticLabel }}</strong>
          <small>Most observed genetic code</small>
      </q-card>
    </div>

    <div class="section-grid section-grid--content">
      <div>
        <AnalyticsCard :items="cropStore.cycleAverages" :analytics-width="analyticsWidth" />

        <q-card flat class="surface-card panel-card mt-4">
          <div class="section-header">
            <div>
              <p class="section-header__eyebrow">Genetics</p>
              <h2>Genetic registry</h2>
            </div>
            <q-chip size="sm" outline color="primary">{{ filteredGenetics.length }} genetics</q-chip>
          </div>

          <div v-if="!filteredGenetics.length" class="empty-state">No genetic line matches the current filter.</div>
          <div v-else class="table-shell">
            <header class="table-head">
              <span>Code</span>
              <span>Name</span>
              <span>Vendor</span>
              <span>Metadata</span>
              <span>Actions</span>
            </header>

            <article v-for="genetic in filteredGenetics" :key="genetic.id" class="table-row">
              <div>
                <strong>{{ genetic.code }}</strong>
              </div>
              <div>
                <strong>{{ genetic.name }}</strong>
              </div>
              <div>
                <strong>{{ genetic.vendor || 'Internal' }}</strong>
              </div>
              <div>
                <p class="metadata-preview">{{ summarizeMetadata(genetic.metadata) }}</p>
              </div>
              <div class="action-stack">
                <q-btn size="sm" outline color="primary" @click="startEdit(genetic)">Edit</q-btn>
                <q-btn size="sm" flat color="negative" @click="removeGenetic(genetic)">Delete</q-btn>
              </div>
            </article>
          </div>
        </q-card>
      </div>

      <div>
        <q-card flat class="surface-card panel-card">
          <div class="section-header section-header--compact">
            <div>
              <p class="section-header__eyebrow">Genetic editor</p>
              <h2>{{ mode === 'create' ? 'Create genetic' : 'Update genetic' }}</h2>
            </div>
            <q-btn v-if="mode === 'edit'" flat color="primary" @click="resetForm">New</q-btn>
          </div>

          <form class="form-grid" @submit.prevent="submitGenetic">
            <q-input v-model="form.code" label="Code" outlined :disabled="saving" />
            <q-input v-model="form.name" label="Name" outlined :disabled="saving" />
            <q-input v-model="form.vendor" label="Vendor" outlined :disabled="saving" />
            <q-input
              v-model="form.metadata"
              label="Metadata JSON"
              type="textarea"
              autogrow
              outlined
              :disabled="saving"
            />
            <q-btn type="submit" color="primary" unelevated rounded class="full-width" :loading="saving">
              {{ mode === 'create' ? 'Create genetic' : 'Save changes' }}
            </q-btn>
          </form>
        </q-card>

        <CommentsCard :entries="cropStore.latestEntries" class="mt-4" />
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import AnalyticsCard from './AnalyticsCard.vue'
import CommentsCard from './CommentsCard.vue'
import { useCropStore } from '../../stores/useCropStore'
import type { GeneticDto } from '../../types/api'

const props = defineProps<{
  search: string
}>()

const $q = useQuasar()
const cropStore = useCropStore()
const saving = ref(false)
const mode = ref<'create' | 'edit'>('create')
const form = reactive({
  iri: '',
  code: '',
  name: '',
  vendor: '',
  metadata: '{\n  "lineage": "Hybrid"\n}',
})

const filteredGenetics = computed(() => {
  const needle = props.search.trim().toLowerCase()

  if (!needle) {
    return cropStore.genetics
  }

  return cropStore.genetics.filter((genetic) => [
    genetic.code,
    genetic.name,
    genetic.vendor ?? '',
    JSON.stringify(genetic.metadata),
  ].join(' ').toLowerCase().includes(needle))
})

const totalCompletedCycles = computed(() => cropStore.cycleAverages.reduce((sum, item) => sum + item.completedCycles, 0))
const topGeneticLabel = computed(() => {
  if (!cropStore.cycleAverages.length) {
    return '—'
  }

  return [...cropStore.cycleAverages].sort((left, right) => right.completedCycles - left.completedCycles)[0].geneticCode
})

const maxAverageCycleDays = computed(() => cropStore.cycleAverages.length ? Math.max(...cropStore.cycleAverages.map((item) => item.averageCycleDays)) : 1)

function analyticsWidth(value: number) {
  return Math.max((value / maxAverageCycleDays.value) * 100, 14)
}

function summarizeMetadata(metadata: Record<string, unknown>) {
  const entries = Object.entries(metadata)

  if (!entries.length) {
    return 'No metadata'
  }

  return entries.slice(0, 3).map(([key, value]) => `${key}: ${String(value)}`).join(' · ')
}

function resetForm() {
  mode.value = 'create'
  form.iri = ''
  form.code = ''
  form.name = ''
  form.vendor = ''
  form.metadata = '{\n  "lineage": "Hybrid"\n}'
}

function startEdit(genetic: GeneticDto) {
  mode.value = 'edit'
  form.iri = genetic['@id']
  form.code = genetic.code
  form.name = genetic.name
  form.vendor = genetic.vendor ?? ''
  form.metadata = JSON.stringify(genetic.metadata, null, 2)
}

async function submitGenetic() {
  saving.value = true

  try {
    const payload = {
      code: form.code.trim(),
      name: form.name.trim(),
      vendor: form.vendor.trim() || null,
      metadata: JSON.parse(form.metadata) as Record<string, unknown>,
    }

    if (!payload.code || !payload.name) {
      throw new Error('Code and name are required.')
    }

    if (mode.value === 'create') {
      await cropStore.createGenetic(payload)
      $q.notify({ type: 'positive', message: 'Genetic created.', position: 'top-right', timeout: 3000 })
    } else {
      await cropStore.updateGenetic(form.iri, payload)
      $q.notify({ type: 'positive', message: 'Genetic updated.', position: 'top-right', timeout: 3000 })
    }

    resetForm()
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'Unable to save the genetic.',
      position: 'top-right',
      timeout: 4000,
    })
  } finally {
    saving.value = false
  }
}

async function removeGenetic(genetic: GeneticDto) {
  if (!window.confirm(`Delete genetic ${genetic.code}?`)) {
    return
  }

  await cropStore.deleteGenetic(genetic['@id'])
  $q.notify({ type: 'positive', message: 'Genetic deleted.', position: 'top-right', timeout: 3000 })

  if (form.iri === genetic['@id']) {
    resetForm()
  }
}
</script>

<style scoped>
.surface-card {
  border: 1px solid #dbe4ea;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(23, 35, 45, 0.06);
}

.metric-card,
.panel-card {
  padding: 18px;
}

.metric-card {
  display: grid;
  gap: 4px;
}

.section-grid {
  display: grid;
  gap: 12px;
}

.section-grid--metrics {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.section-grid--content {
  grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
  align-items: start;
}

.metric-card__label {
  color: #78909c;
  font-size: 0.78rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.metric-card strong {
  color: #37474f;
  font-size: 1.75rem;
  font-weight: 600;
}

.metric-card small {
  color: #607d8b;
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
  font-size: 1.3rem;
  font-weight: 500;
}

.table-shell {
  display: grid;
  gap: 10px;
}

.table-head,
.table-row {
  display: grid;
  grid-template-columns: 90px minmax(180px, 1.3fr) 120px minmax(180px, 1.2fr) 120px;
  gap: 12px;
  align-items: center;
}

.table-head {
  padding: 0 14px 8px;
  color: #90a4ae;
  font-size: 0.76rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.table-row {
  padding: 14px;
  border: 1px solid #e4e8ec;
  border-radius: 10px;
  background: #fafbfc;
}

.table-row strong {
  display: block;
  font-size: 0.96rem;
  font-weight: 600;
}

.metadata-preview {
  margin: 0;
  color: #78909c;
}

.form-grid {
  display: grid;
  gap: 14px;
}

.action-stack {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.empty-state {
  padding: 18px;
  border: 1px dashed #ccd6dd;
  border-radius: 8px;
  background: #fafcfd;
  color: #78909c;
  text-align: center;
}

@media (max-width: 1279px) {
  .table-head {
    display: none;
  }

  .table-row {
    grid-template-columns: 1fr;
  }

  .section-grid--content {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 959px) {
  .section-grid--metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .section-grid--metrics {
    grid-template-columns: 1fr;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
