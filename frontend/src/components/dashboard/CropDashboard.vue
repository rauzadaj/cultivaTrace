<template>
  <v-app>
    <div class="dashboard-shell">
      <section class="hero-panel">
        <div>
          <p class="eyebrow">Cultivation intelligence</p>
          <h1>Observabilite culturale temps reel</h1>
          <p class="hero-copy">
            Supervision continue des cycles, du journal append-only et des varietes avec rafraichissement automatise.
          </p>
        </div>

        <div class="hero-controls">
          <v-text-field
            :model-value="userStore.apiBaseUrl"
            label="API base URL"
            variant="solo-filled"
            density="comfortable"
            hide-details
            @update:model-value="userStore.setApiBaseUrl(String($event))"
          />
          <v-text-field
            :model-value="userStore.operatorLabel"
            label="Operateur"
            variant="solo-filled"
            density="comfortable"
            hide-details
            @update:model-value="userStore.setOperatorLabel(String($event))"
          />
          <div class="hero-meta">
            <span>{{ userStore.operatorLabel }}</span>
            <span>{{ syncLabel }}</span>
          </div>
        </div>
      </section>

      <section class="metrics-grid">
        <article class="metric-card">
          <p>Lots actifs</p>
          <strong>{{ cropStore.activeCrops.length }}</strong>
          <span>cycles hors recolte</span>
        </article>
        <article class="metric-card">
          <p>Journaux recents</p>
          <strong>{{ cropStore.journalEntries.length }}</strong>
          <span>entrees telemetriques</span>
        </article>
        <article class="metric-card">
          <p>Rendement moyen</p>
          <strong>{{ cropStore.averageYield ?? '—' }}</strong>
          <span>grammes recoltes</span>
        </article>
        <article class="metric-card">
          <p>Varietes analysees</p>
          <strong>{{ cropStore.cycleAverages.length }}</strong>
          <span>moyennes de cycle agregees</span>
        </article>
      </section>

      <v-alert v-if="cropStore.error" type="error" variant="tonal" class="mb-4">
        {{ cropStore.error }}
      </v-alert>

      <section class="content-grid">
        <v-card rounded="xl" class="panel-card">
          <template #title>
            <div class="panel-header">
              <div>
                <p class="panel-kicker">Active crops</p>
                <h2>Suivi des lots</h2>
              </div>
              <v-btn color="primary" variant="flat" :loading="cropStore.loading" @click="cropStore.loadDashboard">
                Synchroniser
              </v-btn>
            </div>
          </template>

          <v-card-text class="panel-body">
            <div v-if="!cropStore.activeCrops.length" class="empty-state">Aucun lot actif disponible.</div>
            <div v-for="crop in cropStore.activeCrops" :key="crop.id" class="crop-tile">
              <div class="crop-tile__head">
                <div>
                  <h3>{{ crop.displayName }}</h3>
                  <p>{{ crop.genetic.code }} · {{ crop.genetic.name }}</p>
                </div>
                <v-chip size="small" color="primary" variant="tonal">{{ stageLabel(crop.currentStage) }}</v-chip>
              </div>

              <div class="crop-tile__meta">
                <span>Lot {{ crop.batchCode }}</span>
                <span>Semis {{ formatDate(crop.seededAt) }}</span>
              </div>

              <QuickActionButtons :crop-iri="crop['@id']" :disabled="cropStore.loading" />
            </div>
          </v-card-text>
        </v-card>

        <v-card rounded="xl" class="panel-card">
          <template #title>
            <div class="panel-header">
              <div>
                <p class="panel-kicker">Genetic analytics</p>
                <h2>Cycle moyen par variete</h2>
              </div>
            </div>
          </template>

          <v-card-text class="panel-body">
            <div v-if="!cropStore.cycleAverages.length" class="empty-state">Aucune moyenne calculee.</div>
            <div v-for="item in cropStore.cycleAverages" :key="item.geneticId" class="analytics-row">
              <div>
                <strong>{{ item.geneticCode }}</strong>
                <p>{{ item.geneticName }}</p>
              </div>
              <div class="analytics-row__stats">
                <span>{{ item.averageCycleDays.toFixed(1) }} j</span>
                <small>{{ item.completedCycles }} cycles</small>
              </div>
            </div>
          </v-card-text>
        </v-card>

        <v-card rounded="xl" class="panel-card panel-card--wide">
          <template #title>
            <div class="panel-header">
              <div>
                <p class="panel-kicker">Append-only log</p>
                <h2>Journal recent</h2>
              </div>
            </div>
          </template>

          <v-card-text class="panel-body">
            <div v-if="!cropStore.latestEntries.length" class="empty-state">Aucune entree journal disponible.</div>
            <div v-for="entry in cropStore.latestEntries" :key="entry.id" class="journal-row">
              <div class="journal-row__time">
                <span>{{ formatDate(entry.occurredAt) }}</span>
                <small>{{ entry.crop.displayName }}</small>
              </div>
              <div class="journal-row__content">
                <strong>{{ entryLabel(entry.type) }}</strong>
                <p>{{ entry.notes ?? 'Mesure terrain synchronisee sans note.' }}</p>
              </div>
              <div class="journal-row__metrics">
                <span v-if="entry.phLevel?.value">pH {{ entry.phLevel.value.toFixed(2) }}</span>
                <span v-if="entry.nutrientConcentration?.ppm">
                  {{ entry.nutrientConcentration.ppm }} {{ entry.nutrientConcentration.unit }}
                </span>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </section>
    </div>
  </v-app>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import QuickActionButtons from './QuickActionButtons.vue'
import { useCropStore } from '../../stores/useCropStore'
import { useUserStore } from '../../stores/useUserStore'

const cropStore = useCropStore()
const userStore = useUserStore()

const syncLabel = computed(() => {
  if (!cropStore.lastSyncedAt) {
    return 'Jamais synchronise'
  }

  return `Synchro ${new Date(cropStore.lastSyncedAt).toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  })}`
})

function formatDate(value: string) {
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function stageLabel(stage: string) {
  return {
    seedling: 'Seedling',
    veg: 'Veg',
    flower: 'Flower',
    harvest: 'Harvest',
  }[stage] ?? stage
}

function entryLabel(type: string) {
  return {
    irrigation: 'Arrosage',
    fertilization: 'Fertilisation',
    environment_check: 'Controle environnement',
    stage_transition: 'Transition de stade',
    observation: 'Observation',
  }[type] ?? type
}

onMounted(async () => {
  await cropStore.loadDashboard()
  cropStore.startRealtimePolling()
})

onUnmounted(() => {
  cropStore.stopRealtimePolling()
})
</script>

<style scoped>
:global(body) {
  margin: 0;
  font-family: "Manrope", "Segoe UI", sans-serif;
  background:
    radial-gradient(circle at top left, rgba(25, 91, 57, 0.14), transparent 32%),
    radial-gradient(circle at right, rgba(217, 119, 6, 0.12), transparent 28%),
    linear-gradient(180deg, #f6f2e8 0%, #efe7d7 100%);
  color: #1f2937;
}

.dashboard-shell {
  min-height: 100vh;
  padding: 20px;
}

.hero-panel {
  display: grid;
  gap: 20px;
  padding: 24px;
  border-radius: 28px;
  background:
    linear-gradient(135deg, rgba(25, 91, 57, 0.98), rgba(92, 60, 30, 0.92)),
    linear-gradient(180deg, #195b39, #6f4e37);
  color: #fff9ee;
  box-shadow: 0 24px 80px rgba(50, 34, 19, 0.18);
}

.eyebrow,
.panel-kicker {
  margin: 0 0 8px;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  font-size: 0.74rem;
  opacity: 0.78;
}

h1 {
  margin: 0;
  font-size: clamp(2.2rem, 6vw, 4.2rem);
  line-height: 0.94;
  max-width: 10ch;
}

.hero-copy {
  max-width: 48ch;
  color: rgba(255, 249, 238, 0.86);
}

.hero-controls {
  display: grid;
  gap: 12px;
}

.hero-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 0.94rem;
  color: rgba(255, 249, 238, 0.82);
}

.metrics-grid,
.content-grid {
  display: grid;
  gap: 16px;
  margin-top: 16px;
}

.metrics-grid {
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}

.metric-card {
  padding: 18px;
  border-radius: 22px;
  background: rgba(255, 253, 247, 0.88);
  border: 1px solid rgba(80, 58, 35, 0.09);
  backdrop-filter: blur(10px);
}

.metric-card p,
.metric-card span {
  margin: 0;
  color: #6b7280;
}

.metric-card strong {
  display: block;
  margin: 8px 0 4px;
  font-size: 2rem;
  color: #111827;
}

.panel-card {
  border: 1px solid rgba(80, 58, 35, 0.09);
  background: rgba(255, 253, 247, 0.94);
}

.panel-card--wide {
  grid-column: 1 / -1;
}

.panel-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.panel-header h2,
.crop-tile h3 {
  margin: 0;
}

.panel-body {
  display: grid;
  gap: 14px;
}

.crop-tile,
.analytics-row,
.journal-row {
  padding: 16px;
  border-radius: 20px;
  background: #fffaf1;
  border: 1px solid rgba(100, 76, 48, 0.08);
}

.crop-tile__head,
.analytics-row,
.journal-row {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
}

.crop-tile__head p,
.analytics-row p,
.journal-row p {
  margin: 4px 0 0;
  color: #6b7280;
}

.crop-tile__meta,
.journal-row__metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin: 12px 0;
  color: #6b7280;
  font-size: 0.92rem;
}

.analytics-row__stats {
  text-align: right;
}

.analytics-row__stats span {
  display: block;
  font-size: 1.25rem;
  font-weight: 700;
}

.journal-row {
  flex-direction: column;
}

.journal-row__time small,
.analytics-row__stats small {
  color: #6b7280;
}

.empty-state {
  padding: 24px;
  border-radius: 18px;
  background: rgba(243, 239, 228, 0.72);
  color: #6b7280;
  text-align: center;
}

@media (min-width: 960px) {
  .dashboard-shell {
    padding: 28px;
  }

  .hero-panel {
    grid-template-columns: minmax(0, 1.5fr) minmax(320px, 1fr);
    align-items: end;
  }

  .content-grid {
    grid-template-columns: 1.35fr 0.95fr;
  }

  .journal-row {
    flex-direction: row;
    align-items: center;
  }
}
</style>
