<template>
  <v-app>
    <div class="dashboard-shell">
      <div class="ambient ambient--emerald" />
      <div class="ambient ambient--amber" />
      <div class="ambient ambient--grid" />

      <header class="topbar">
        <div class="brand-lockup">
          <div class="brand-lockup__mark">CT</div>
          <div>
            <p class="eyebrow">CultivaTrace Control Cloud</p>
            <strong>Realtime cultivation operations</strong>
          </div>
        </div>

        <div class="topbar__status">
          <div class="status-chip">
            <span class="status-chip__dot" />
            <span>Operator {{ userStore.operatorLabel }}</span>
          </div>
          <div class="status-chip">
            <span class="status-chip__dot status-chip__dot--amber" />
            <span>{{ syncLabel }}</span>
          </div>
        </div>
      </header>

      <section class="hero-grid">
        <article class="hero-card panel-surface">
          <div class="hero-card__copy">
            <p class="eyebrow">SaaS operations layer</p>
            <h1>Un cockpit cultural premium, lisible et reellement vivant.</h1>
            <p class="hero-copy">
              Vue unifiee des lots, du journal append-only et des signaux agronomiques avec une
              presentation plus proche d'un produit SaaS 2026 que d'une console d'administration.
            </p>
          </div>

          <div class="hero-highlights">
            <article class="highlight-tile">
              <span>Fleet active</span>
              <strong>{{ cropStore.activeCrops.length }}</strong>
              <small>{{ totalLots }} lots suivis</small>
            </article>
            <article class="highlight-tile">
              <span>Yield benchmark</span>
              <strong>{{ cropStore.averageYield ?? '—' }}</strong>
              <small>grammes par cycle recolte</small>
            </article>
            <article class="highlight-tile">
              <span>Solution chemistry</span>
              <strong>{{ averagePhLabel }}</strong>
              <small>{{ averagePpmLabel }}</small>
            </article>
          </div>

          <div class="hero-footer">
            <v-btn
              color="primary"
              size="large"
              variant="flat"
              rounded="pill"
              class="hero-footer__cta"
              :loading="cropStore.loading"
              @click="cropStore.loadDashboard"
            >
              Rafraichir le cockpit
            </v-btn>

            <div class="hero-footer__notes">
              <span>Journal append-only protege</span>
              <span>{{ freshestEntryLabel }}</span>
            </div>
          </div>
        </article>

        <aside class="command-card panel-surface">
          <div class="panel-heading">
            <div>
              <p class="panel-kicker">Mission control</p>
              <h2>Connexion et posture live</h2>
            </div>
            <span class="panel-badge">JWT secured</span>
          </div>

          <form class="command-form" @submit.prevent>
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
            <v-text-field
              :model-value="userStore.token"
              label="JWT token"
              variant="solo-filled"
              density="comfortable"
              hide-details
              clearable
              type="password"
              autocomplete="current-password"
              @update:model-value="userStore.setToken(String($event ?? ''))"
            />
          </form>

          <div class="stage-strip">
            <div
              v-for="node in stageNodes"
              :key="node.key"
              class="stage-strip__item"
              :class="{ 'stage-strip__item--active': node.count > 0 }"
            >
              <span>{{ node.label }}</span>
              <strong>{{ node.count }}</strong>
            </div>
          </div>

          <div class="system-brief">
            <div class="system-brief__row">
              <span>API mode</span>
              <strong>{{ apiModeLabel }}</strong>
            </div>
            <div class="system-brief__row">
              <span>Polling</span>
              <strong>15s refresh cadence</strong>
            </div>
            <div class="system-brief__row">
              <span>Analytics leader</span>
              <strong>{{ analyticsLeaderLabel }}</strong>
            </div>
          </div>
        </aside>
      </section>

      <v-alert v-if="cropStore.error" type="error" variant="tonal" class="mt-4">
        {{ cropStore.error }}
      </v-alert>

      <section class="signal-grid">
        <article class="signal-card panel-surface">
          <div class="panel-heading">
            <div>
              <p class="panel-kicker">Pulse</p>
              <h2>Fleet health</h2>
            </div>
            <span class="signal-badge">{{ activeRatioLabel }}</span>
          </div>

          <div class="signal-visual">
            <div
              v-for="node in stageNodes"
              :key="`bar-${node.key}`"
              class="signal-bar"
            >
              <div class="signal-bar__label">
                <span>{{ node.label }}</span>
                <small>{{ node.count }}</small>
              </div>
              <div class="signal-bar__track">
                <div class="signal-bar__fill" :style="{ width: `${node.width}%` }" />
              </div>
            </div>
          </div>
        </article>

        <article class="signal-card panel-surface">
          <div class="panel-heading">
            <div>
              <p class="panel-kicker">Telemetry</p>
              <h2>Signal chemistry</h2>
            </div>
          </div>

          <div class="stat-stack">
            <div class="stat-stack__item">
              <span>pH moyen recent</span>
              <strong>{{ averagePhLabel }}</strong>
            </div>
            <div class="stat-stack__item">
              <span>Nutrition moyenne</span>
              <strong>{{ averagePpmLabel }}</strong>
            </div>
            <div class="stat-stack__item">
              <span>Events terrain</span>
              <strong>{{ cropStore.journalEntries.length }}</strong>
            </div>
          </div>
        </article>

        <article class="signal-card panel-surface">
          <div class="panel-heading">
            <div>
              <p class="panel-kicker">Yield</p>
              <h2>Benchmark genetique</h2>
            </div>
          </div>

          <div class="leader-card">
            <strong>{{ analyticsLeaderLabel }}</strong>
            <p>{{ analyticsLeaderSubLabel }}</p>
          </div>

          <div class="mini-metrics">
            <div class="mini-metrics__item">
              <span>Cycles completes</span>
              <strong>{{ totalCompletedCycles }}</strong>
            </div>
            <div class="mini-metrics__item">
              <span>Varietes scorees</span>
              <strong>{{ cropStore.cycleAverages.length }}</strong>
            </div>
          </div>
        </article>

        <article class="signal-card panel-surface">
          <div class="panel-heading">
            <div>
              <p class="panel-kicker">Stream</p>
              <h2>Realtime relay</h2>
            </div>
            <span class="panel-badge panel-badge--ghost">Append-only</span>
          </div>

          <div class="relay-list">
            <div v-for="entry in relayEntries" :key="entry.id" class="relay-list__item">
              <strong>{{ entryLabel(entry.type) }}</strong>
              <span>{{ entry.crop.displayName }}</span>
            </div>
          </div>
        </article>
      </section>

      <section class="workspace-grid">
        <article class="workspace-panel panel-surface">
          <div class="panel-heading">
            <div>
              <p class="panel-kicker">Crop deck</p>
              <h2>Surface d'operations</h2>
            </div>
            <span class="panel-badge">{{ totalLots }} tracked lots</span>
          </div>

          <div v-if="spotlightCrop" class="spotlight-card">
            <div>
              <p class="spotlight-card__eyebrow">Spotlight crop</p>
              <h3>{{ spotlightCrop.displayName }}</h3>
              <p>{{ spotlightCrop.genetic.code }} · {{ spotlightCrop.genetic.name }}</p>
            </div>
            <div class="spotlight-card__meta">
              <span>{{ stageLabel(spotlightCrop.currentStage) }}</span>
              <strong>{{ formatDate(spotlightCrop.seededAt) }}</strong>
            </div>
          </div>

          <div class="crop-grid">
            <article
              v-for="(crop, index) in cropStore.activeCrops"
              :key="crop.id"
              class="crop-card"
              :style="{ animationDelay: `${index * 70}ms` }"
            >
              <div class="crop-card__header">
                <div>
                  <h3>{{ crop.displayName }}</h3>
                  <p>{{ crop.genetic.code }} · {{ crop.genetic.name }}</p>
                </div>
                <span class="stage-pill" :class="`stage-pill--${crop.currentStage}`">
                  {{ stageLabel(crop.currentStage) }}
                </span>
              </div>

              <div class="progress-meter">
                <div class="progress-meter__track">
                  <div class="progress-meter__fill" :style="{ width: `${cropProgress(crop.currentStage)}%` }" />
                </div>
                <div class="progress-meter__labels">
                  <span>Batch {{ crop.batchCode }}</span>
                  <span>Semis {{ formatDate(crop.seededAt) }}</span>
                </div>
              </div>

              <QuickActionButtons :crop-iri="crop['@id']" :disabled="cropStore.loading" />
            </article>
          </div>

          <div v-if="!cropStore.activeCrops.length" class="empty-state">
            Aucun lot actif disponible.
          </div>
        </article>

        <aside class="workspace-aside">
          <article class="workspace-panel panel-surface">
            <div class="panel-heading">
              <div>
                <p class="panel-kicker">Analytics</p>
                <h2>Leaderboard varietal</h2>
              </div>
            </div>

            <div v-if="!cropStore.cycleAverages.length" class="empty-state">
              Aucune moyenne calculee.
            </div>
            <div v-else class="analytics-list">
              <div v-for="item in cropStore.cycleAverages" :key="item.geneticId" class="analytics-item">
                <div class="analytics-item__header">
                  <div>
                    <strong>{{ item.geneticCode }}</strong>
                    <p>{{ item.geneticName }}</p>
                  </div>
                  <span>{{ item.averageCycleDays.toFixed(1) }} j</span>
                </div>
                <div class="analytics-item__track">
                  <div
                    class="analytics-item__fill"
                    :style="{ width: `${analyticsWidth(item.averageCycleDays)}%` }"
                  />
                </div>
                <small>{{ item.completedCycles }} cycles completes</small>
              </div>
            </div>
          </article>

          <article class="workspace-panel panel-surface">
            <div class="panel-heading">
              <div>
                <p class="panel-kicker">Append-only stream</p>
                <h2>Journal recent</h2>
              </div>
              <span class="panel-badge panel-badge--ghost">{{ cropStore.latestEntries.length }} events</span>
            </div>

            <div v-if="!cropStore.latestEntries.length" class="empty-state">
              Aucune entree journal disponible.
            </div>
            <div v-else class="journal-feed">
              <article v-for="entry in cropStore.latestEntries" :key="entry.id" class="journal-feed__item">
                <div class="journal-feed__marker" />
                <div class="journal-feed__body">
                  <div class="journal-feed__header">
                    <strong>{{ entryLabel(entry.type) }}</strong>
                    <span>{{ formatDate(entry.occurredAt) }}</span>
                  </div>
                  <p>{{ entry.crop.displayName }}</p>
                  <small>{{ entry.notes ?? 'Mesure terrain synchronisee sans note.' }}</small>
                  <div class="journal-feed__metrics">
                    <span v-if="entry.phLevel?.value">pH {{ entry.phLevel.value.toFixed(2) }}</span>
                    <span v-if="entry.nutrientConcentration?.ppm">
                      {{ entry.nutrientConcentration.ppm }} {{ entry.nutrientConcentration.unit }}
                    </span>
                  </div>
                </div>
              </article>
            </div>
          </article>
        </aside>
      </section>
    </div>
  </v-app>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted } from 'vue'
import QuickActionButtons from './QuickActionButtons.vue'
import { useCropStore } from '../../stores/useCropStore'
import { useUserStore } from '../../stores/useUserStore'
import type { CropDto, JournalEntryDto } from '../../types/api'

const cropStore = useCropStore()
const userStore = useUserStore()

const orderedStages: CropDto['currentStage'][] = ['seedling', 'veg', 'flower', 'harvest']

const totalLots = computed(() => cropStore.crops.length)
const relayEntries = computed(() => cropStore.latestEntries.slice(0, 4))
const spotlightCrop = computed(() => cropStore.activeCrops[0] ?? cropStore.harvestedCrops[0] ?? null)
const freshestEntry = computed(() => cropStore.latestEntries[0] ?? null)
const totalCompletedCycles = computed(() => cropStore.cycleAverages.reduce((sum, item) => sum + item.completedCycles, 0))

const averagePh = computed(() => {
  const values = cropStore.latestEntries
    .map((entry) => entry.phLevel?.value)
    .filter((value): value is number => typeof value === 'number')

  if (!values.length) {
    return null
  }

  return values.reduce((sum, value) => sum + value, 0) / values.length
})

const averagePpm = computed(() => {
  const values = cropStore.latestEntries
    .map((entry) => entry.nutrientConcentration?.ppm)
    .filter((value): value is number => typeof value === 'number')

  if (!values.length) {
    return null
  }

  return Math.round(values.reduce((sum, value) => sum + value, 0) / values.length)
})

const stageNodes = computed(() => {
  const total = Math.max(totalLots.value, 1)

  return orderedStages.map((stage) => {
    const count = cropStore.crops.filter((crop) => crop.currentStage === stage).length

    return {
      key: stage,
      label: stageLabel(stage),
      count,
      width: Math.max((count / total) * 100, count > 0 ? 14 : 6),
    }
  })
})

const activeRatioLabel = computed(() => {
  if (!totalLots.value) {
    return '0% active'
  }

  return `${Math.round((cropStore.activeCrops.length / totalLots.value) * 100)}% active`
})

const averagePhLabel = computed(() => averagePh.value?.toFixed(2) ?? 'No pH')
const averagePpmLabel = computed(() => averagePpm.value ? `${averagePpm.value} ppm moyen` : 'No EC/ppm')
const freshestEntryLabel = computed(() => freshestEntry.value ? `Dernier signal ${formatDate(freshestEntry.value.occurredAt)}` : 'Aucun signal recent')
const apiModeLabel = computed(() => userStore.normalizedApiBase.startsWith('/api') ? 'Local proxy /api' : userStore.normalizedApiBase)

const analyticsLeader = computed(() => {
  if (!cropStore.cycleAverages.length) {
    return null
  }

  return [...cropStore.cycleAverages].sort((left, right) => right.completedCycles - left.completedCycles)[0]
})

const analyticsLeaderLabel = computed(() => analyticsLeader.value ? analyticsLeader.value.geneticCode : 'No analytics')
const analyticsLeaderSubLabel = computed(() => {
  if (!analyticsLeader.value) {
    return 'Aucune serie de cycle completee.'
  }

  return `${analyticsLeader.value.completedCycles} cycles completes · ${analyticsLeader.value.averageCycleDays.toFixed(1)} jours de moyenne`
})

const maxAverageCycleDays = computed(() => {
  if (!cropStore.cycleAverages.length) {
    return 1
  }

  return Math.max(...cropStore.cycleAverages.map((item) => item.averageCycleDays))
})

const syncLabel = computed(() => {
  if (!cropStore.lastSyncedAt) {
    return 'Jamais synchronise'
  }

  return `Synchro ${new Date(cropStore.lastSyncedAt).toLocaleTimeString('fr-FR', {
    hour: '2-digit',
    minute: '2-digit',
  })}`
})

function cropProgress(stage: CropDto['currentStage']) {
  return ((orderedStages.indexOf(stage) + 1) / orderedStages.length) * 100
}

function analyticsWidth(value: number) {
  return Math.max((value / maxAverageCycleDays.value) * 100, 18)
}

function formatDate(value: string) {
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function stageLabel(stage: CropDto['currentStage'] | string) {
  return {
    seedling: 'Seedling',
    veg: 'Vegetative',
    flower: 'Flower',
    harvest: 'Harvest',
  }[stage] ?? stage
}

function entryLabel(type: JournalEntryDto['type'] | string) {
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
@import url('https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;700&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap');

:global(body) {
  margin: 0;
  font-family: "IBM Plex Sans", "Segoe UI", sans-serif;
  background:
    radial-gradient(circle at top left, rgba(42, 211, 138, 0.18), transparent 22rem),
    radial-gradient(circle at top right, rgba(245, 158, 11, 0.16), transparent 20rem),
    linear-gradient(180deg, #07111b 0%, #0d1724 46%, #0b1220 100%);
  color: #ebf1f7;
}

.dashboard-shell {
  --panel-border: rgba(180, 205, 226, 0.12);
  --panel-sheen: rgba(255, 255, 255, 0.04);
  --panel-bg: rgba(9, 20, 32, 0.72);
  --panel-bg-strong: rgba(10, 22, 36, 0.9);
  --text-soft: rgba(220, 231, 240, 0.72);
  --mint: #2ad38a;
  --amber: #ffb347;
  --sky: #7dd3fc;
  position: relative;
  min-height: 100vh;
  padding: 22px;
  overflow: hidden;
}

.ambient {
  position: absolute;
  inset: auto;
  pointer-events: none;
}

.ambient--emerald {
  top: -120px;
  left: -140px;
  width: 360px;
  height: 360px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(42, 211, 138, 0.32), transparent 66%);
  filter: blur(8px);
}

.ambient--amber {
  right: -120px;
  top: 240px;
  width: 320px;
  height: 320px;
  border-radius: 50%;
  background: radial-gradient(circle, rgba(255, 179, 71, 0.24), transparent 70%);
  filter: blur(12px);
}

.ambient--grid {
  inset: 0;
  background-image:
    linear-gradient(rgba(255, 255, 255, 0.03) 1px, transparent 1px),
    linear-gradient(90deg, rgba(255, 255, 255, 0.03) 1px, transparent 1px);
  background-size: 56px 56px;
  mask-image: linear-gradient(180deg, rgba(0, 0, 0, 0.42), transparent 92%);
}

.topbar,
.hero-grid,
.signal-grid,
.workspace-grid {
  position: relative;
  z-index: 1;
}

.topbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.brand-lockup {
  display: flex;
  align-items: center;
  gap: 14px;
}

.brand-lockup__mark {
  display: grid;
  place-items: center;
  width: 50px;
  height: 50px;
  border-radius: 16px;
  background: linear-gradient(135deg, rgba(42, 211, 138, 0.9), rgba(125, 211, 252, 0.72));
  color: #06111c;
  font-family: "Space Grotesk", sans-serif;
  font-size: 1.1rem;
  font-weight: 700;
  box-shadow: 0 16px 40px rgba(42, 211, 138, 0.24);
}

.eyebrow,
.panel-kicker,
.spotlight-card__eyebrow {
  margin: 0 0 8px;
  color: var(--text-soft);
  letter-spacing: 0.18em;
  text-transform: uppercase;
  font-size: 0.72rem;
}

.brand-lockup strong,
h1,
h2,
h3,
.highlight-tile strong,
.signal-card strong,
.spotlight-card strong {
  font-family: "Space Grotesk", "IBM Plex Sans", sans-serif;
}

.topbar__status {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 10px;
}

.status-chip,
.panel-badge {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  padding: 0 14px;
  border: 1px solid var(--panel-border);
  border-radius: 999px;
  background: rgba(12, 27, 43, 0.65);
  color: #f1f7fb;
  backdrop-filter: blur(18px);
}

.panel-badge {
  color: var(--text-soft);
  font-size: 0.82rem;
}

.panel-badge--ghost {
  background: rgba(255, 255, 255, 0.03);
}

.status-chip__dot {
  width: 9px;
  height: 9px;
  border-radius: 50%;
  background: var(--mint);
  box-shadow: 0 0 16px rgba(42, 211, 138, 0.85);
}

.status-chip__dot--amber {
  background: var(--amber);
  box-shadow: 0 0 16px rgba(255, 179, 71, 0.8);
}

.hero-grid,
.workspace-grid {
  display: grid;
  gap: 18px;
}

.hero-grid {
  margin-top: 8px;
}

.panel-surface {
  border: 1px solid var(--panel-border);
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.05), transparent 24%),
    linear-gradient(180deg, var(--panel-bg), var(--panel-bg-strong));
  box-shadow:
    inset 0 1px 0 var(--panel-sheen),
    0 22px 60px rgba(2, 8, 17, 0.4);
  backdrop-filter: blur(24px);
}

.hero-card,
.command-card,
.signal-card,
.workspace-panel {
  border-radius: 28px;
  padding: 24px;
}

.hero-card {
  overflow: hidden;
}

.hero-card__copy {
  max-width: 58ch;
}

h1 {
  margin: 0;
  max-width: 12ch;
  font-size: clamp(2.8rem, 6vw, 5.4rem);
  line-height: 0.92;
  letter-spacing: -0.05em;
}

h2,
h3 {
  margin: 0;
  letter-spacing: -0.03em;
}

.hero-copy,
.leader-card p,
.analytics-item p,
.crop-card p,
.journal-feed__body p,
.journal-feed__body small,
.signal-bar__label small,
.highlight-tile small,
.system-brief__row span {
  color: var(--text-soft);
}

.hero-highlights {
  display: grid;
  gap: 14px;
  margin-top: 24px;
}

.highlight-tile {
  padding: 16px 18px;
  border-radius: 20px;
  border: 1px solid rgba(173, 211, 235, 0.12);
  background: rgba(255, 255, 255, 0.05);
}

.highlight-tile span,
.mini-metrics__item span,
.stat-stack__item span {
  display: block;
  margin-bottom: 8px;
  color: var(--text-soft);
  font-size: 0.82rem;
  text-transform: uppercase;
  letter-spacing: 0.14em;
}

.highlight-tile strong,
.stat-stack__item strong,
.mini-metrics__item strong {
  display: block;
  font-size: 1.95rem;
}

.hero-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 14px;
  align-items: center;
  justify-content: space-between;
  margin-top: 24px;
}

.hero-footer__cta {
  min-width: 220px;
}

.hero-footer__notes {
  display: grid;
  gap: 6px;
  color: var(--text-soft);
  font-size: 0.92rem;
}

.panel-heading {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 14px;
  margin-bottom: 18px;
}

.command-form {
  display: grid;
  gap: 12px;
}

.stage-strip {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 10px;
  margin-top: 18px;
}

.stage-strip__item {
  padding: 14px;
  border-radius: 18px;
  border: 1px solid rgba(173, 211, 235, 0.09);
  background: rgba(255, 255, 255, 0.04);
}

.stage-strip__item span {
  display: block;
  color: var(--text-soft);
  font-size: 0.8rem;
}

.stage-strip__item strong {
  display: block;
  margin-top: 4px;
  font-size: 1.4rem;
}

.stage-strip__item--active {
  box-shadow: inset 0 0 0 1px rgba(42, 211, 138, 0.18);
}

.system-brief {
  display: grid;
  gap: 12px;
  margin-top: 18px;
}

.system-brief__row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding-top: 12px;
  border-top: 1px solid rgba(173, 211, 235, 0.08);
}

.system-brief__row strong {
  color: #f8fbff;
  font-size: 0.94rem;
}

.signal-grid {
  display: grid;
  gap: 18px;
  margin-top: 18px;
}

.signal-badge {
  color: var(--mint);
  font-size: 0.82rem;
}

.signal-visual {
  display: grid;
  gap: 14px;
}

.signal-bar__label {
  display: flex;
  justify-content: space-between;
  margin-bottom: 7px;
}

.signal-bar__track,
.progress-meter__track,
.analytics-item__track {
  overflow: hidden;
  height: 10px;
  border-radius: 999px;
  background: rgba(173, 211, 235, 0.08);
}

.signal-bar__fill,
.progress-meter__fill,
.analytics-item__fill {
  height: 100%;
  border-radius: inherit;
  background: linear-gradient(90deg, var(--mint), var(--sky));
  box-shadow: 0 0 22px rgba(42, 211, 138, 0.34);
}

.stat-stack,
.mini-metrics {
  display: grid;
  gap: 12px;
}

.stat-stack__item,
.mini-metrics__item,
.leader-card {
  padding: 16px 18px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(173, 211, 235, 0.09);
}

.leader-card {
  margin-bottom: 12px;
}

.leader-card strong {
  font-size: 1.4rem;
}

.relay-list {
  display: grid;
  gap: 12px;
}

.relay-list__item {
  display: flex;
  flex-direction: column;
  gap: 3px;
  padding: 14px 16px;
  border-radius: 18px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(173, 211, 235, 0.08);
}

.relay-list__item span {
  color: var(--text-soft);
}

.workspace-grid {
  margin-top: 18px;
}

.workspace-panel {
  min-height: 100%;
}

.spotlight-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 18px;
  margin-bottom: 18px;
  padding: 18px 20px;
  border-radius: 24px;
  background:
    radial-gradient(circle at right top, rgba(255, 179, 71, 0.2), transparent 35%),
    linear-gradient(135deg, rgba(42, 211, 138, 0.13), rgba(125, 211, 252, 0.08));
  border: 1px solid rgba(173, 211, 235, 0.12);
}

.spotlight-card__meta {
  display: grid;
  gap: 8px;
  text-align: right;
}

.crop-grid,
.workspace-aside {
  display: grid;
  gap: 16px;
}

.crop-card {
  padding: 18px;
  border-radius: 24px;
  background:
    linear-gradient(180deg, rgba(255, 255, 255, 0.05), transparent 18%),
    rgba(6, 17, 29, 0.68);
  border: 1px solid rgba(173, 211, 235, 0.08);
  animation: rise-in 0.6s ease both;
}

.crop-card__header,
.analytics-item__header,
.journal-feed__header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.stage-pill {
  display: inline-flex;
  align-items: center;
  padding: 8px 12px;
  border-radius: 999px;
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  background: rgba(255, 255, 255, 0.06);
  border: 1px solid rgba(173, 211, 235, 0.1);
}

.stage-pill--seedling {
  color: #c4f1a8;
}

.stage-pill--veg {
  color: var(--mint);
}

.stage-pill--flower {
  color: #ffd37d;
}

.stage-pill--harvest {
  color: var(--sky);
}

.progress-meter {
  margin: 16px 0 18px;
}

.progress-meter__labels {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-top: 8px;
  color: var(--text-soft);
  font-size: 0.86rem;
}

.analytics-list,
.journal-feed {
  display: grid;
  gap: 14px;
}

.analytics-item {
  padding: 16px 18px;
  border-radius: 22px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(173, 211, 235, 0.08);
}

.analytics-item__header {
  margin-bottom: 10px;
}

.analytics-item small {
  display: block;
  margin-top: 8px;
  color: var(--text-soft);
}

.journal-feed__item {
  position: relative;
  display: grid;
  grid-template-columns: 18px minmax(0, 1fr);
  gap: 12px;
}

.journal-feed__marker {
  width: 12px;
  height: 12px;
  margin-top: 7px;
  border-radius: 50%;
  background: linear-gradient(135deg, var(--mint), var(--sky));
  box-shadow: 0 0 24px rgba(42, 211, 138, 0.35);
}

.journal-feed__body {
  padding: 14px 16px;
  border-radius: 20px;
  background: rgba(255, 255, 255, 0.04);
  border: 1px solid rgba(173, 211, 235, 0.08);
}

.journal-feed__body p {
  margin: 8px 0 6px;
}

.journal-feed__body small {
  display: block;
}

.journal-feed__metrics {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 12px;
}

.journal-feed__metrics span {
  padding: 7px 10px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.06);
  color: #f3f9fd;
  font-size: 0.8rem;
}

.empty-state {
  padding: 28px;
  border-radius: 22px;
  border: 1px dashed rgba(173, 211, 235, 0.16);
  background: rgba(255, 255, 255, 0.03);
  color: var(--text-soft);
  text-align: center;
}

@keyframes rise-in {
  from {
    opacity: 0;
    transform: translateY(20px);
  }

  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (min-width: 840px) {
  .hero-grid {
    grid-template-columns: minmax(0, 1.35fr) minmax(360px, 0.92fr);
  }

  .hero-highlights {
    grid-template-columns: repeat(3, minmax(0, 1fr));
  }

  .signal-grid {
    grid-template-columns: repeat(4, minmax(0, 1fr));
  }

  .workspace-grid {
    grid-template-columns: minmax(0, 1.32fr) minmax(360px, 0.88fr);
    align-items: start;
  }

  .crop-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 839px) {
  .topbar,
  .spotlight-card,
  .panel-heading,
  .hero-footer,
  .crop-card__header,
  .analytics-item__header,
  .journal-feed__header {
    flex-direction: column;
    align-items: flex-start;
  }

  .topbar__status {
    justify-content: flex-start;
  }

  .spotlight-card__meta {
    text-align: left;
  }

  h1 {
    max-width: 100%;
  }
}
</style>
