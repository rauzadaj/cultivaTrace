<template>
  <div>
    <v-row dense class="mb-1">
      <v-col cols="12" md="6" xl="3">
        <v-card flat class="surface-card metric-card">
          <span class="metric-card__label">Genetics</span>
          <strong>{{ filteredGenetics.length }}</strong>
          <small>Available lines in the catalog</small>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" xl="3">
        <v-card flat class="surface-card metric-card">
          <span class="metric-card__label">Analytics rows</span>
          <strong>{{ cropStore.cycleAverages.length }}</strong>
          <small>Computed mean cycle durations</small>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" xl="3">
        <v-card flat class="surface-card metric-card">
          <span class="metric-card__label">Completed cycles</span>
          <strong>{{ totalCompletedCycles }}</strong>
          <small>Included in aggregation</small>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" xl="3">
        <v-card flat class="surface-card metric-card">
          <span class="metric-card__label">Top line</span>
          <strong>{{ topGeneticLabel }}</strong>
          <small>Most observed genetic code</small>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense>
      <v-col cols="12" xl="8">
        <AnalyticsCard :items="cropStore.cycleAverages" :analytics-width="analyticsWidth" />

        <v-card flat class="surface-card panel-card mt-4">
          <div class="section-header">
            <div>
              <p class="section-header__eyebrow">Genetics</p>
              <h2>Genetic registry</h2>
            </div>
            <v-chip size="small" variant="tonal" color="primary">{{ filteredGenetics.length }} genetics</v-chip>
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
                <v-btn size="small" variant="tonal" color="primary" @click="startEdit(genetic)">Edit</v-btn>
                <v-btn size="small" variant="text" color="error" @click="removeGenetic(genetic)">Delete</v-btn>
              </div>
            </article>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" xl="4">
        <v-card flat class="surface-card panel-card">
          <div class="section-header section-header--compact">
            <div>
              <p class="section-header__eyebrow">Genetic editor</p>
              <h2>{{ mode === 'create' ? 'Create genetic' : 'Update genetic' }}</h2>
            </div>
            <v-btn v-if="mode === 'edit'" variant="text" color="primary" @click="resetForm">New</v-btn>
          </div>

          <form class="form-grid" @submit.prevent="submitGenetic">
            <v-text-field v-model="form.code" label="Code" variant="outlined" density="comfortable" :disabled="saving" />
            <v-text-field v-model="form.name" label="Name" variant="outlined" density="comfortable" :disabled="saving" />
            <v-text-field v-model="form.vendor" label="Vendor" variant="outlined" density="comfortable" :disabled="saving" />
            <v-textarea
              v-model="form.metadata"
              label="Metadata JSON"
              rows="7"
              variant="outlined"
              density="comfortable"
              :disabled="saving"
            />
            <v-btn type="submit" color="primary" variant="flat" rounded="lg" block :loading="saving">
              {{ mode === 'create' ? 'Create genetic' : 'Save changes' }}
            </v-btn>
          </form>
        </v-card>

        <CommentsCard :entries="cropStore.latestEntries" class="mt-4" />
      </v-col>
    </v-row>

    <v-snackbar v-model="snackbar.visible" color="success" timeout="3000">
      {{ snackbar.message }}
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import AnalyticsCard from './AnalyticsCard.vue'
import CommentsCard from './CommentsCard.vue'
import { useCropStore } from '../../stores/useCropStore'
import type { GeneticDto } from '../../types/api'

const props = defineProps<{
  search: string
}>()

const cropStore = useCropStore()
const saving = ref(false)
const mode = ref<'create' | 'edit'>('create')
const snackbar = reactive({ visible: false, message: '' })
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
      snackbar.message = 'Genetic created.'
    } else {
      await cropStore.updateGenetic(form.iri, payload)
      snackbar.message = 'Genetic updated.'
    }

    snackbar.visible = true
    resetForm()
  } finally {
    saving.value = false
  }
}

async function removeGenetic(genetic: GeneticDto) {
  if (!window.confirm(`Delete genetic ${genetic.code}?`)) {
    return
  }

  await cropStore.deleteGenetic(genetic['@id'])
  snackbar.message = 'Genetic deleted.'
  snackbar.visible = true

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
}

@media (max-width: 640px) {
  .section-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
