<template>
  <div>
    <v-row dense class="mb-1">
      <v-col v-for="card in overviewMetrics" :key="card.label" cols="12" md="6" xl="3">
        <v-card flat class="surface-card metric-card">
          <span class="metric-card__label">{{ card.label }}</span>
          <strong>{{ card.value }}</strong>
          <small>{{ card.hint }}</small>
        </v-card>
      </v-col>
    </v-row>

    <v-row dense>
      <v-col cols="12" xl="8">
        <v-card flat class="surface-card panel-card">
          <div class="section-header">
            <div>
              <p class="section-header__eyebrow">Overview</p>
              <h2>Live operations deck</h2>
            </div>
            <div class="section-actions">
              <v-btn color="primary" variant="flat" rounded="lg" @click="goTo('dashboard-lots')">Add lot</v-btn>
              <v-btn color="secondary" variant="tonal" rounded="lg" @click="goTo('dashboard-services')">Manage services</v-btn>
              <v-btn color="info" variant="tonal" rounded="lg" @click="goTo('dashboard-analytics')">Manage genetics</v-btn>
            </div>
          </div>

          <div class="overview-grid">
            <div class="overview-list">
              <header class="table-head">
                <span>Lot</span>
                <span>Stage</span>
                <span>Seeded</span>
                <span>Actions</span>
              </header>

              <article v-for="crop in visibleCrops" :key="crop.id" class="table-row">
                <div class="table-row__primary">
                  <span class="lot-indicator" :class="`lot-indicator--${crop.currentStage}`" />
                  <div>
                    <strong>{{ crop.displayName }}</strong>
                    <p>{{ crop.batchCode }} · {{ crop.genetic.code }}</p>
                  </div>
                </div>
                <div>
                  <span class="lot-chip" :class="`lot-chip--${crop.currentStage}`">{{ stageLabel(crop.currentStage) }}</span>
                </div>
                <div>
                  <strong>{{ formatDate(crop.seededAt) }}</strong>
                  <p>{{ crop.genetic.name }}</p>
                </div>
                <div>
                  <QuickActionButtons :crop-iri="crop['@id']" :disabled="cropStore.loading" />
                </div>
              </article>
            </div>

            <div class="overview-side">
              <WorkspaceCard
                :api-base-url="userStore.apiBaseUrl"
                :operator-label="userStore.operatorLabel"
                :user-email="userStore.userEmail"
                @update:api-base-url="userStore.setApiBaseUrl"
                @update:operator-label="userStore.setOperatorLabel"
              />
              <ServicesCard :items="serviceItems" class="mt-4" />
            </div>
          </div>
        </v-card>
      </v-col>

      <v-col cols="12" xl="4">
        <v-card flat class="surface-card panel-card">
          <div class="section-header section-header--compact">
            <div>
              <p class="section-header__eyebrow">Journal</p>
              <h2>Append-only note</h2>
            </div>
          </div>

          <form class="form-grid" @submit.prevent="submitJournal">
            <v-select
              v-model="journalForm.crop"
              label="Lot"
              :items="cropOptions"
              item-title="label"
              item-value="value"
              variant="outlined"
              density="comfortable"
              :disabled="!cropOptions.length || savingJournal"
            />
            <v-select
              v-model="journalForm.type"
              label="Event type"
              :items="journalTypeOptions"
              item-title="label"
              item-value="value"
              variant="outlined"
              density="comfortable"
              :disabled="savingJournal"
            />
            <v-text-field
              v-model="journalForm.occurredAt"
              label="Occurred at"
              type="datetime-local"
              variant="outlined"
              density="comfortable"
              :disabled="savingJournal"
            />
            <v-textarea
              v-model="journalForm.notes"
              label="Note"
              rows="4"
              variant="outlined"
              density="comfortable"
              :disabled="savingJournal"
            />
            <v-btn type="submit" color="primary" variant="flat" rounded="lg" block :loading="savingJournal" :disabled="!cropOptions.length">
              Add journal entry
            </v-btn>
          </form>
        </v-card>

        <CommentsCard :entries="cropStore.latestEntries" class="mt-4" />
      </v-col>
    </v-row>

    <v-row dense class="mt-1">
      <v-col cols="12" xl="8">
        <AnalyticsCard :items="cropStore.cycleAverages" :analytics-width="analyticsWidth" />
      </v-col>
      <v-col cols="12" xl="4">
        <v-card flat class="surface-card panel-card">
          <div class="section-header section-header--compact">
            <div>
              <p class="section-header__eyebrow">Library</p>
              <h2>Genetic catalog</h2>
            </div>
            <v-chip size="small" variant="tonal" color="primary">{{ cropStore.genetics.length }} genetics</v-chip>
          </div>

          <div class="catalog-list">
            <article v-for="genetic in cropStore.genetics.slice(0, 5)" :key="genetic.id" class="catalog-item">
              <div>
                <strong>{{ genetic.code }}</strong>
                <p>{{ genetic.name }}</p>
              </div>
              <small>{{ genetic.vendor || 'Internal catalog' }}</small>
            </article>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <v-snackbar v-model="snackbar.visible" color="success" timeout="3000">
      {{ snackbar.message }}
    </v-snackbar>
  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import AnalyticsCard from './AnalyticsCard.vue'
import CommentsCard from './CommentsCard.vue'
import QuickActionButtons from './QuickActionButtons.vue'
import ServicesCard from './ServicesCard.vue'
import WorkspaceCard from './WorkspaceCard.vue'
import { useCropStore } from '../../stores/useCropStore'
import { useUserStore } from '../../stores/useUserStore'
import type { JournalEntryDto } from '../../types/api'

const props = defineProps<{
  search: string
}>()

const cropStore = useCropStore()
const userStore = useUserStore()
const router = useRouter()
const savingJournal = ref(false)
const snackbar = reactive({ visible: false, message: '' })
const journalForm = reactive({
  crop: '',
  type: 'observation' as JournalEntryDto['type'],
  notes: '',
  occurredAt: currentDateTimeInput(),
})

const journalTypeOptions: Array<{ label: string; value: JournalEntryDto['type'] }> = [
  { label: 'Observation', value: 'observation' },
  { label: 'Irrigation', value: 'irrigation' },
  { label: 'Fertilization', value: 'fertilization' },
  { label: 'Environment check', value: 'environment_check' },
]

const cropOptions = computed(() => cropStore.crops.map((crop) => ({
  label: `${crop.displayName} · ${crop.batchCode}`,
  value: crop['@id'],
})))

const visibleCrops = computed(() => {
  const needle = props.search.trim().toLowerCase()

  if (!needle) {
    return cropStore.crops.slice(0, 6)
  }

  return cropStore.crops.filter((crop) => [
    crop.displayName,
    crop.batchCode,
    crop.genetic.code,
    crop.genetic.name,
    crop.currentStage,
  ].join(' ').toLowerCase().includes(needle)).slice(0, 6)
})
const serviceItems = computed(() => cropStore.services.map((service) => ({
  title: service.name,
  description: service.description,
  icon: service.icon,
  tone: service.tone,
  status: service.statusLabel,
})))

const totalCompletedCycles = computed(() => cropStore.cycleAverages.reduce((sum, item) => sum + item.completedCycles, 0))
const averagePhLabel = computed(() => {
  const values = cropStore.latestEntries.map((entry) => entry.phLevel?.value).filter((value): value is number => typeof value === 'number')

  if (!values.length) {
    return '—'
  }

  return (values.reduce((sum, value) => sum + value, 0) / values.length).toFixed(2)
})

const averagePpmLabel = computed(() => {
  const values = cropStore.latestEntries.map((entry) => entry.nutrientConcentration?.ppm).filter((value): value is number => typeof value === 'number')

  if (!values.length) {
    return '—'
  }

  return `${Math.round(values.reduce((sum, value) => sum + value, 0) / values.length)} ppm`
})

const overviewMetrics = computed(() => [
  { label: 'Active lots', value: cropStore.activeCrops.length, hint: 'Seedling, veg and flower' },
  { label: 'Harvested lots', value: cropStore.harvestedCrops.length, hint: `${totalCompletedCycles.value} completed cycles` },
  { label: 'Journal events', value: cropStore.journalEntries.length, hint: 'Immutable activity stream' },
  { label: 'Average chemistry', value: `${averagePhLabel.value} · ${averagePpmLabel.value}`, hint: 'Across latest field captures' },
])

const maxAverageCycleDays = computed(() => cropStore.cycleAverages.length ? Math.max(...cropStore.cycleAverages.map((item) => item.averageCycleDays)) : 1)

watch(() => cropStore.crops, (crops) => {
  if (!journalForm.crop && crops.length) {
    journalForm.crop = crops[0]['@id']
  }
}, { deep: true, immediate: true })

function analyticsWidth(value: number) {
  return Math.max((value / maxAverageCycleDays.value) * 100, 14)
}

function currentDateTimeInput() {
  const now = new Date()
  const offset = now.getTimezoneOffset()
  const normalizedDate = new Date(now.getTime() - offset * 60_000)

  return normalizedDate.toISOString().slice(0, 16)
}

function stageLabel(stage: string) {
  return {
    seedling: 'Seedling',
    veg: 'Veg',
    flower: 'Flower',
    harvest: 'Harvest',
  }[stage] ?? stage
}

function formatDate(value: string) {
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function toIsoString(value: string) {
  if (!value) {
    throw new Error('A date value is required.')
  }

  return new Date(value).toISOString()
}

async function submitJournal() {
  savingJournal.value = true

  try {
    await cropStore.createJournalEntry({
      crop: journalForm.crop,
      type: journalForm.type,
      occurredAt: toIsoString(journalForm.occurredAt),
      notes: journalForm.notes.trim() || null,
      metadata: {
        source: 'dashboard',
        enteredBy: userStore.userEmail || userStore.operatorLabel,
      },
    })

    journalForm.notes = ''
    journalForm.occurredAt = currentDateTimeInput()
    snackbar.message = 'Journal entry appended.'
    snackbar.visible = true
  } finally {
    savingJournal.value = false
  }
}

function goTo(name: 'dashboard-lots' | 'dashboard-services' | 'dashboard-analytics') {
  void router.push({ name })
}
</script>

<style scoped>
.surface-card {
  border: 1px solid #dbe4ea;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(23, 35, 45, 0.06);
}

.metric-card {
  display: grid;
  gap: 4px;
  padding: 18px;
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

.panel-card {
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
  font-size: 1.3rem;
  font-weight: 500;
}

.section-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.overview-grid {
  display: grid;
  grid-template-columns: minmax(0, 1.4fr) minmax(300px, 0.8fr);
  gap: 18px;
}

.overview-side,
.catalog-list {
  display: grid;
  gap: 12px;
}

.overview-list {
  display: grid;
  gap: 10px;
}

.table-head,
.table-row {
  display: grid;
  grid-template-columns: minmax(180px, 1.3fr) 100px 140px minmax(220px, 1.2fr);
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

.table-row strong,
.catalog-item strong {
  display: block;
  font-size: 0.96rem;
  font-weight: 600;
}

.table-row p,
.catalog-item p,
.catalog-item small {
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

.catalog-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 12px;
  border: 1px solid #e4e8ec;
  border-radius: 10px;
  background: #fafbfc;
}

@media (max-width: 1499px) {
  .overview-grid {
    grid-template-columns: 1fr;
  }
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
