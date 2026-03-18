<template>
  <div class="plant-detail">
    <div v-if="plantsStore.loading && !plant" class="detail-skeleton">
      <q-skeleton height="96px" />
      <q-skeleton height="320px" />
    </div>

    <template v-else-if="plant">
      <div class="detail-header" :class="{ 'detail-header--sticky': isMobile }">
        <div>
          <p class="detail-header__eyebrow">Plant detail</p>
          <h1>{{ plantName }}</h1>
        </div>
        <div class="detail-header__actions">
          <plant-stage-chip :stage="plant.stage" />
          <q-btn flat round icon="mdi-dots-horizontal" />
        </div>
      </div>

      <q-tabs
        v-model="activeTab"
        dense
        inline-label
        no-caps
        class="detail-tabs"
        active-color="primary"
      >
        <q-tab name="info" label="Infos" />
        <q-tab name="timeline" label="Timeline" />
        <q-tab name="inputs" label="Intrants" />
        <q-tab name="photos" label="Photos" />
      </q-tabs>

      <div v-if="isMobile" class="detail-mobile">
        <q-tab-panels v-model="activeTab" animated keep-alive>
          <q-tab-panel name="info">
            <c-card class="detail-card">
              <dl class="info-grid">
                <div><dt>Genetique</dt><dd>{{ strainLabel }}</dd></div>
                <div><dt>Salle</dt><dd>{{ roomLabel }}</dd></div>
                <div><dt>RFID</dt><dd>{{ plant.rfidTag || 'Non renseigne' }}</dd></div>
                <div><dt>Germination</dt><dd>{{ formatDate(plant.germinatedAt) }}</dd></div>
              </dl>
            </c-card>
          </q-tab-panel>

          <q-tab-panel name="timeline">
            <div class="timeline-list">
              <c-card v-for="entry in timelineEntries" :key="entry.id" class="timeline-item">
                <div class="timeline-item__head">
                  <alert-badge :severity="eventSeverity(entry.eventType)">{{ entry.eventType }}</alert-badge>
                  <small>{{ formatDateTime(entry.occurredAt) }}</small>
                </div>
                <div class="timeline-item__body">
                  <q-icon :name="timelineIcon(entry.eventType)" size="20px" class="timeline-item__icon" />
                  <p>{{ entry.notes || eventContext(entry) }}</p>
                </div>
              </c-card>
            </div>
          </q-tab-panel>

          <q-tab-panel name="inputs">
            <c-card>Les intrants sont traces dans la timeline append-only.</c-card>
          </q-tab-panel>

          <q-tab-panel name="photos">
            <c-card>{{ plantHasPhotos ? 'Des photos sont associees a certains events.' : 'Aucune photo disponible pour ce plant.' }}</c-card>
          </q-tab-panel>
        </q-tab-panels>

        <div class="detail-mobile__cta">
          <c-btn variant="secondary" class="full-width" @click="emitStageDialog">Changer stade</c-btn>
        </div>
      </div>

      <div v-else class="detail-desktop">
        <aside class="detail-desktop__side">
          <c-card class="detail-card">
            <div class="section-head">
              <div>
                <p class="section-head__eyebrow">Infos</p>
                <h2>Contexte culture</h2>
              </div>
            </div>
            <dl class="info-grid">
              <div><dt>Genetique</dt><dd>{{ strainLabel }}</dd></div>
              <div><dt>Salle</dt><dd>{{ roomLabel }}</dd></div>
              <div><dt>RFID</dt><dd>{{ plant.rfidTag || 'Non renseigne' }}</dd></div>
              <div><dt>Germination</dt><dd>{{ formatDate(plant.germinatedAt) }}</dd></div>
            </dl>
            <c-btn variant="secondary" class="q-mt-md full-width" @click="emitStageDialog">Changer stade</c-btn>
          </c-card>
        </aside>

        <section class="detail-desktop__main">
          <div class="section-head">
            <div>
              <p class="section-head__eyebrow">Timeline</p>
              <h2>Events terrain</h2>
            </div>
          </div>
          <div class="timeline-list">
            <c-card v-for="entry in timelineEntries" :key="entry.id" class="timeline-item">
              <div class="timeline-item__head">
                <alert-badge :severity="eventSeverity(entry.eventType)">{{ entry.eventType }}</alert-badge>
                <small>{{ formatDateTime(entry.occurredAt) }}</small>
              </div>
              <div class="timeline-item__body">
                <q-icon :name="timelineIcon(entry.eventType)" size="20px" class="timeline-item__icon" />
                <p>{{ entry.notes || eventContext(entry) }}</p>
              </div>
            </c-card>
          </div>
        </section>
      </div>
    </template>

    <c-card v-else class="detail-empty">
      <q-icon name="mdi-sprout-outline" size="32px" />
      <strong>Plant introuvable</strong>
      <span>Recharge la liste ou retourne a l'ecran plants pour selectionner un autre lot.</span>
    </c-card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import type { PlantEvent } from '@/types/api'
import AlertBadge from '../../components/ui/AlertBadge.vue'
import CBtn from '../../components/ui/CBtn.vue'
import CCard from '../../components/ui/CCard.vue'
import PlantStageChip from '../../components/ui/PlantStageChip.vue'
import { useDisplay } from '../../composables/useDisplay'
import { usePlantsStore } from '../../stores/plants'

const route = useRoute()
const plantsStore = usePlantsStore()
const { xs } = useDisplay()

const activeTab = ref('info')
const isMobile = computed(() => xs.value)
const plant = computed(() => plantsStore.currentPlant)
const plantName = computed(() => plantsStore.plantName(plant.value))
const roomLabel = computed(() => plantsStore.roomLabel(plant.value))
const strainLabel = computed(() => plantsStore.strainLabel(plant.value))
const timelineEntries = computed(() => plantsStore.currentEvents)
const plantHasPhotos = computed(() => timelineEntries.value.some((entry) => (entry.photoUrls?.length ?? 0) > 0))

async function loadPlant() {
  const id = String(route.params.id)
  await Promise.all([plantsStore.fetchPlant(id), plantsStore.fetchEvents(id)])
}

onMounted(async () => {
  try {
    await loadPlant()
  } catch (error) {
    console.error('Plant detail load failed', error)
  }
})

watch(() => route.params.id, async (id) => {
  if (!id) {
    return
  }

  try {
    await loadPlant()
  } catch (error) {
    console.error('Plant detail reload failed', error)
  }
})

function emitStageDialog() {
  window.dispatchEvent(new CustomEvent('plant-stage:open'))
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  })
}

function formatDateTime(value: string) {
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function eventSeverity(type: PlantEvent['eventType']) {
  if (type === 'destruction_confirmed') return 'critical'
  if (type === 'harvest' || type === 'destruction_intent') return 'warning'

  return 'healthy'
}

function timelineIcon(type: PlantEvent['eventType']) {
  if (type === 'germination') return 'mdi-sprout-outline'
  if (type === 'stage_change') return 'mdi-swap-horizontal'
  if (type === 'input_record') return 'mdi-flask-outline'
  if (type === 'room_move') return 'mdi-door-open'
  if (type === 'harvest') return 'mdi-basket-outline'
  if (type === 'photo') return 'mdi-camera-outline'

  return 'mdi-note-outline'
}

function eventContext(entry: PlantEvent) {
  if (entry.eventType === 'stage_change' && entry.payload && 'to' in entry.payload) {
    return `Passage au stade ${String(entry.payload.to)}`
  }

  return 'Event captured from field workflow.'
}
</script>

<style scoped lang="scss">
@use '../../css/breakpoints.sass' as bp;
.plant-detail { display: grid; gap: 16px; }
.detail-skeleton { display: grid; gap: 16px; }
.detail-skeleton :deep(.q-skeleton) { border-radius: 16px; }
.detail-header { display: flex; align-items: center; justify-content: space-between; gap: 16px; flex-wrap: wrap; padding: 8px 0 8px; background: #f7f8fa; }
.detail-header--sticky { position: sticky; top: 0; z-index: 20; }
.detail-header h1 { margin: 0; font-size: 1.5rem; font-weight: 600; line-height: 1.1; }
.detail-header__eyebrow, .section-head__eyebrow { margin: 0 0 6px; color: #718096; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.12em; }
.detail-header__actions { display: flex; align-items: center; gap: 8px; }
.detail-tabs { position: sticky; top: 64px; z-index: 19; background: rgba(247, 248, 250, 0.98); border-bottom: 1px solid #e2e8f0; }
.detail-card { padding: 4px; }
.detail-empty { display: grid; justify-items: start; gap: 10px; }
.info-grid { display: grid; gap: 16px; margin: 0; }
.info-grid dt { color: #718096; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.1em; }
.info-grid dd { margin: 6px 0 0; font-weight: 600; }
.timeline-list { display: grid; gap: 12px; }
.timeline-item { display: grid; gap: 10px; }
.timeline-item__head { display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.timeline-item__body { display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 10px; align-items: start; }
.timeline-item__icon { margin-top: 2px; color: #1b6b3a; }
.timeline-item p, .timeline-item small { margin: 0; }
.timeline-item p { color: #2d3748; line-height: 1.5; }
.timeline-item small { color: #718096; }
.detail-mobile__cta { position: sticky; bottom: 84px; z-index: 18; }
.detail-desktop { display: grid; grid-template-columns: 1fr; gap: 16px; }
.section-head { margin-bottom: 12px; }
.section-head h2 { margin: 0; font-size: 1.2rem; font-weight: 600; }
@include bp.desktop { .detail-desktop { grid-template-columns: minmax(280px, 360px) minmax(0, 1fr); align-items: start; } }
</style>
