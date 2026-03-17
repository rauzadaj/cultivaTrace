<template>
  <div>
    <div class="section-grid section-grid--metrics mb-1">
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Visible lots</span>
          <strong>{{ filteredCrops.length }}</strong>
          <small>Filtered from the current workspace</small>
      </q-card>
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Active ratio</span>
          <strong>{{ activeRatioLabel }}</strong>
          <small>Lots not yet harvested</small>
      </q-card>
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Journal events</span>
          <strong>{{ cropStore.journalEntries.length }}</strong>
          <small>Append-only field captures</small>
      </q-card>
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Avg yield</span>
          <strong>{{ cropStore.averageYield ?? '—' }}</strong>
          <small>Across harvested cycles</small>
      </q-card>
    </div>

    <div class="section-grid section-grid--content">
      <div>
        <q-card flat class="surface-card panel-card">
          <div class="section-header">
            <div>
              <p class="section-header__eyebrow">Lots</p>
              <h2>Lot registry</h2>
            </div>
            <div class="section-actions">
              <q-chip size="sm" outline color="primary">{{ filteredCrops.length }} visible</q-chip>
              <q-btn color="primary" unelevated rounded :loading="cropStore.loading" @click="cropStore.loadDashboard">Refresh</q-btn>
            </div>
          </div>

          <div v-if="!filteredCrops.length" class="empty-state">No lot matches the current filter.</div>
          <div v-else class="table-shell">
            <header class="table-head">
              <span>Lot</span>
              <span>Genetic</span>
              <span>Stage</span>
              <span>Seeded</span>
              <span>Lifecycle</span>
              <span>Field actions</span>
            </header>

            <article v-for="crop in filteredCrops" :key="crop.id" class="table-row">
              <div class="table-row__primary">
                <span class="lot-indicator" :class="`lot-indicator--${crop.currentStage}`" />
                <div>
                  <strong>{{ crop.displayName }}</strong>
                  <p>{{ crop.batchCode }}</p>
                </div>
              </div>
              <div>
                <strong>{{ crop.genetic.code }}</strong>
                <p>{{ crop.genetic.name }}</p>
              </div>
              <div>
                <span class="lot-chip" :class="`lot-chip--${crop.currentStage}`">{{ stageLabel(crop.currentStage) }}</span>
              </div>
              <div>
                <strong>{{ formatDate(crop.seededAt) }}</strong>
                <p>{{ crop.harvestedAt ? `Harvest ${formatDate(crop.harvestedAt)}` : 'In progress' }}</p>
              </div>
              <div class="action-stack">
                <q-btn size="sm" outline color="primary" @click="startEdit(crop)">Edit</q-btn>
                <q-btn
                  v-if="nextTransitionFor(crop)"
                  size="sm"
                  outline
                  color="secondary"
                  @click="handleTransition(crop)"
                >
                  {{ nextTransitionFor(crop)?.label }}
                </q-btn>
                <q-btn size="sm" flat color="negative" @click="removeCrop(crop)">Delete</q-btn>
              </div>
              <div>
                <QuickActionButtons :crop-iri="crop['@id']" :disabled="cropStore.loading" />
              </div>
            </article>
          </div>
        </q-card>
      </div>

      <div>
        <q-card flat class="surface-card panel-card">
          <div class="section-header section-header--compact">
            <div>
              <p class="section-header__eyebrow">Lot editor</p>
              <h2>{{ mode === 'create' ? 'Create lot' : 'Update lot' }}</h2>
            </div>
            <q-btn v-if="mode === 'edit'" flat color="primary" @click="resetForm">New</q-btn>
          </div>

          <form class="form-grid" @submit.prevent="submitCrop">
            <q-input v-model="form.displayName" label="Display name" outlined :disabled="saving" />
            <q-input v-model="form.batchCode" label="Batch code" outlined :disabled="saving" />
            <q-select
              v-model="form.genetic"
              label="Genetic"
              :items="geneticOptions"
              option-label="label"
              option-value="value"
              outlined
              emit-value
              map-options
              :disabled="saving || !geneticOptions.length"
            />
            <q-input
              v-model="form.seededAt"
              label="Seeded at"
              type="datetime-local"
              outlined
              :disabled="saving"
            />
            <q-btn type="submit" color="primary" unelevated rounded class="full-width" :loading="saving" :disabled="!geneticOptions.length">
              {{ mode === 'create' ? 'Create lot' : 'Save changes' }}
            </q-btn>
          </form>
        </q-card>

        <TelemetryCard
          class="mt-4"
          :journal-count="cropStore.journalEntries.length"
          :average-yield="cropStore.averageYield"
          :average-ph-label="averagePhLabel"
          :active-ratio-label="activeRatioLabel"
        />
      </div>
    </div>

    <q-dialog v-model="harvestDialog">
      <q-card class="harvest-dialog">
        <q-card-section>
          <div class="text-h6">Harvest lot</div>
        </q-card-section>
        <q-card-section>
          <div class="form-grid">
            <q-input
              v-model="harvestForm.finalYieldGrams"
              label="Final yield (g)"
              type="number"
              min="0"
              outlined
            />
            <q-input
              v-model="harvestForm.harvestedAt"
              label="Harvested at"
              type="datetime-local"
              outlined
            />
          </div>
        </q-card-section>
        <q-card-actions align="right" class="q-px-lg q-pb-lg">
          <q-btn flat @click="harvestDialog = false">Cancel</q-btn>
          <q-btn color="primary" unelevated :loading="transitionPending" @click="submitHarvest">Harvest</q-btn>
        </q-card-actions>
      </q-card>
    </q-dialog>

  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import QuickActionButtons from './QuickActionButtons.vue'
import TelemetryCard from './TelemetryCard.vue'
import { useCropStore } from '../../stores/useCropStore'
import type { CropDto } from '../../types/api'

const props = defineProps<{
  search: string
}>()

const $q = useQuasar()
const cropStore = useCropStore()
const saving = ref(false)
const transitionPending = ref(false)
const harvestDialog = ref(false)
const mode = ref<'create' | 'edit'>('create')
const form = reactive({
  iri: '',
  displayName: '',
  batchCode: '',
  genetic: '',
  seededAt: currentDateTimeInput(),
})
const harvestForm = reactive({
  cropId: '',
  finalYieldGrams: '',
  harvestedAt: currentDateTimeInput(),
})

const filteredCrops = computed(() => {
  const needle = props.search.trim().toLowerCase()

  if (!needle) {
    return cropStore.crops
  }

  return cropStore.crops.filter((crop) => [
    crop.displayName,
    crop.batchCode,
    crop.genetic.code,
    crop.genetic.name,
    crop.currentStage,
  ].join(' ').toLowerCase().includes(needle))
})

const geneticOptions = computed(() => cropStore.genetics.map((genetic) => ({
  label: `${genetic.code} · ${genetic.name}`,
  value: genetic['@id'],
})))

const activeRatioLabel = computed(() => cropStore.crops.length ? `${Math.round((cropStore.activeCrops.length / cropStore.crops.length) * 100)}%` : '0%')
const averagePhLabel = computed(() => {
  const values = cropStore.latestEntries.map((entry) => entry.phLevel?.value).filter((value): value is number => typeof value === 'number')

  if (!values.length) {
    return '—'
  }

  return (values.reduce((sum, value) => sum + value, 0) / values.length).toFixed(2)
})

watch(() => cropStore.genetics, (genetics) => {
  if (!form.genetic && genetics.length) {
    form.genetic = genetics[0]['@id']
  }
}, { deep: true, immediate: true })

function currentDateTimeInput() {
  const now = new Date()
  const offset = now.getTimezoneOffset()
  const normalizedDate = new Date(now.getTime() - offset * 60_000)

  return normalizedDate.toISOString().slice(0, 16)
}

function toDateTimeInput(value: string) {
  const date = new Date(value)
  const offset = date.getTimezoneOffset()
  const normalizedDate = new Date(date.getTime() - offset * 60_000)

  return normalizedDate.toISOString().slice(0, 16)
}

function toIsoString(value: string) {
  if (!value) {
    throw new Error('A date value is required.')
  }

  return new Date(value).toISOString()
}

function formatDate(value: string | null) {
  if (!value) {
    return '—'
  }

  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function stageLabel(stage: CropDto['currentStage']) {
  return {
    seedling: 'Seedling',
    veg: 'Veg',
    flower: 'Flower',
    harvest: 'Harvest',
  }[stage]
}

function nextTransitionFor(crop: CropDto) {
  if (crop.currentStage === 'seedling') return { transition: 'start_vegetative' as const, label: 'Move to veg' }
  if (crop.currentStage === 'veg') return { transition: 'start_flowering' as const, label: 'Move to flower' }
  if (crop.currentStage === 'flower') return { transition: 'harvest' as const, label: 'Harvest' }

  return null
}

function resetForm() {
  mode.value = 'create'
  form.iri = ''
  form.displayName = ''
  form.batchCode = ''
  form.genetic = geneticOptions.value[0]?.value ?? ''
  form.seededAt = currentDateTimeInput()
}

function startEdit(crop: CropDto) {
  mode.value = 'edit'
  form.iri = crop['@id']
  form.displayName = crop.displayName
  form.batchCode = crop.batchCode
  form.genetic = crop.genetic['@id']
  form.seededAt = toDateTimeInput(crop.seededAt)
}

async function submitCrop() {
  saving.value = true

  try {
    const payload = {
      displayName: form.displayName.trim(),
      batchCode: form.batchCode.trim(),
      genetic: form.genetic,
      seededAt: toIsoString(form.seededAt),
    }

    if (!payload.displayName || !payload.batchCode || !payload.genetic) {
      throw new Error('Display name, batch code and genetic are required.')
    }

    if (mode.value === 'create') {
      await cropStore.createCrop(payload)
      $q.notify({ type: 'positive', message: 'Lot created.', position: 'top-right', timeout: 3000 })
    } else {
      await cropStore.updateCrop(form.iri, payload)
      $q.notify({ type: 'positive', message: 'Lot updated.', position: 'top-right', timeout: 3000 })
    }

    resetForm()
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'Unable to save the lot.',
      position: 'top-right',
      timeout: 4000,
    })
  } finally {
    saving.value = false
  }
}

async function removeCrop(crop: CropDto) {
  if (!window.confirm(`Delete lot ${crop.displayName}?`)) {
    return
  }

  await cropStore.deleteCrop(crop['@id'])
  $q.notify({ type: 'positive', message: 'Lot deleted.', position: 'top-right', timeout: 3000 })

  if (form.iri === crop['@id']) {
    resetForm()
  }
}

async function handleTransition(crop: CropDto) {
  const nextTransition = nextTransitionFor(crop)

  if (!nextTransition) {
    return
  }

  if (nextTransition.transition === 'harvest') {
    harvestForm.cropId = crop.id
    harvestForm.finalYieldGrams = crop.finalYieldGrams?.toString() ?? ''
    harvestForm.harvestedAt = currentDateTimeInput()
    harvestDialog.value = true

    return
  }

  transitionPending.value = true

  try {
    await cropStore.applyTransition(crop.id, nextTransition.transition)
    $q.notify({
      type: 'positive',
      message: `Lot moved to ${nextTransition.label.replace('Move to ', '')}.`,
      position: 'top-right',
      timeout: 3000,
    })
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'Unable to apply the transition.',
      position: 'top-right',
      timeout: 4000,
    })
  } finally {
    transitionPending.value = false
  }
}

async function submitHarvest() {
  transitionPending.value = true

  try {
    const payload = {
      finalYieldGrams: Number.parseInt(harvestForm.finalYieldGrams, 10),
      harvestedAt: toIsoString(harvestForm.harvestedAt),
    }

    if (!Number.isInteger(payload.finalYieldGrams) || payload.finalYieldGrams < 0) {
      throw new Error('Final yield must be a non-negative integer.')
    }

    await cropStore.applyTransition(harvestForm.cropId, 'harvest', payload)
    harvestDialog.value = false
    $q.notify({ type: 'positive', message: 'Lot harvested.', position: 'top-right', timeout: 3000 })
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'Unable to harvest the lot.',
      position: 'top-right',
      timeout: 4000,
    })
  } finally {
    transitionPending.value = false
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

.section-actions,
.action-stack {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.table-shell {
  display: grid;
  gap: 10px;
}

.table-head,
.table-row {
  display: grid;
  grid-template-columns: minmax(180px, 1.3fr) minmax(140px, 1fr) 100px 140px minmax(180px, 1fr) minmax(240px, 1.2fr);
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

.table-row__primary {
  display: flex;
  align-items: center;
  gap: 12px;
}

.table-row strong {
  display: block;
  font-size: 0.96rem;
  font-weight: 600;
}

.table-row p {
  margin: 0;
  color: #78909c;
}

.lot-indicator {
  width: 12px;
  height: 12px;
  border-radius: 50%;
}

.lot-indicator--seedling {
  background: #8bc34a;
}

.lot-indicator--veg {
  background: #00acc1;
}

.lot-indicator--flower {
  background: #ffb300;
}

.lot-indicator--harvest {
  background: #7cb342;
}

.lot-chip {
  display: inline-flex;
  align-items: center;
  min-height: 28px;
  padding: 0 10px;
  border-radius: 999px;
  font-size: 0.74rem;
  font-weight: 600;
  text-transform: uppercase;
}

.lot-chip--seedling {
  background: #edf7df;
  color: #5c7a24;
}

.lot-chip--veg {
  background: #e0f7fa;
  color: #0b7285;
}

.lot-chip--flower {
  background: #fff7db;
  color: #9a6700;
}

.lot-chip--harvest {
  background: #eef6e3;
  color: #5b7c27;
}

.form-grid {
  display: grid;
  gap: 14px;
}

.empty-state {
  padding: 18px;
  border: 1px dashed #ccd6dd;
  border-radius: 8px;
  background: #fafcfd;
  color: #78909c;
  text-align: center;
}

.harvest-dialog {
  width: min(420px, calc(100vw - 32px));
  border-radius: 16px;
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
