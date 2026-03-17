<template>
  <div class="plant-detail">
    <div v-if="cropStore.loading" class="detail-skeleton">
      <q-skeleton height="96px" />
      <q-skeleton height="320px" />
    </div>

    <template v-else-if="plant">
      <div class="detail-header" :class="{ 'detail-header--sticky': isMobile }">
        <div>
          <p class="detail-header__eyebrow">Plant detail</p>
          <h1>{{ plant.displayName }}</h1>
        </div>
        <div class="detail-header__actions">
          <plant-stage-chip :stage="plant.currentStage" />
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
                <div><dt>Genetic</dt><dd>{{ plant.genetic.code }} · {{ plant.genetic.name }}</dd></div>
                <div><dt>Batch</dt><dd>{{ plant.batchCode }}</dd></div>
                <div><dt>Seeded</dt><dd>{{ formatDate(plant.seededAt) }}</dd></div>
              </dl>
            </c-card>
          </q-tab-panel>

          <q-tab-panel name="timeline">
            <div class="timeline-list">
              <c-card v-for="entry in timelineEntries" :key="entry.id" class="timeline-item">
                <div class="timeline-item__head">
                  <alert-badge :severity="entry.type === 'environment_check' ? 'warning' : 'healthy'">{{ entry.type }}</alert-badge>
                  <small>{{ formatDateTime(entry.occurredAt) }}</small>
                </div>
                <p>{{ entry.notes || 'Event captured from field workflow.' }}</p>
              </c-card>
            </div>
          </q-tab-panel>

          <q-tab-panel name="inputs">
            <c-card>Intrants traces via timeline append-only.</c-card>
          </q-tab-panel>

          <q-tab-panel name="photos">
            <c-card>Aucune photo disponible pour ce plant.</c-card>
          </q-tab-panel>
        </q-tab-panels>

        <div class="detail-mobile__cta">
          <c-btn variant="primary" class="full-width">Changer stade</c-btn>
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
              <div><dt>Genetic</dt><dd>{{ plant.genetic.code }} · {{ plant.genetic.name }}</dd></div>
              <div><dt>Batch</dt><dd>{{ plant.batchCode }}</dd></div>
              <div><dt>Seeded</dt><dd>{{ formatDate(plant.seededAt) }}</dd></div>
            </dl>
            <c-btn variant="primary" class="q-mt-md full-width">Changer stade</c-btn>
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
                <alert-badge :severity="entry.type === 'environment_check' ? 'warning' : 'healthy'">{{ entry.type }}</alert-badge>
                <small>{{ formatDateTime(entry.occurredAt) }}</small>
              </div>
              <p>{{ entry.notes || 'Event captured from field workflow.' }}</p>
            </c-card>
          </div>
        </section>
      </div>
    </template>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute } from 'vue-router'
import AlertBadge from '../../components/ui/AlertBadge.vue'
import CBtn from '../../components/ui/CBtn.vue'
import CCard from '../../components/ui/CCard.vue'
import PlantStageChip from '../../components/ui/PlantStageChip.vue'
import { useDisplay } from '../../composables/useDisplay'
import { useCropStore } from '../../stores/useCropStore'

const route = useRoute()
const cropStore = useCropStore()
const { xs } = useDisplay()

const activeTab = ref('info')
const isMobile = computed(() => xs.value)
const plant = computed(() => cropStore.crops.find((crop) => crop.id === String(route.params.id)))
const timelineEntries = computed(() => cropStore.journalEntries.slice(0, 8))

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
</script>

<style scoped>
.plant-detail {
  display: grid;
  gap: 16px;
}

.detail-skeleton {
  display: grid;
  gap: 16px;
}

.detail-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 16px 0 8px;
  background: #f7f8fa;
}

.detail-header--sticky {
  position: sticky;
  top: 0;
  z-index: 20;
}

.detail-header h1 {
  margin: 0;
  font-size: 1.8rem;
  font-weight: 600;
}

.detail-header__eyebrow,
.section-head__eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.detail-header__actions {
  display: flex;
  align-items: center;
  gap: 8px;
}

.detail-tabs {
  position: sticky;
  top: 72px;
  z-index: 19;
  background: rgba(247, 248, 250, 0.98);
  border-bottom: 1px solid #e2e8f0;
}

.detail-card {
  padding: 4px;
}

.info-grid {
  display: grid;
  gap: 16px;
  margin: 0;
}

.info-grid dt {
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.info-grid dd {
  margin: 6px 0 0;
  font-weight: 600;
}

.timeline-list {
  display: grid;
  gap: 12px;
}

.timeline-item {
  display: grid;
  gap: 10px;
}

.timeline-item__head {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.timeline-item p,
.timeline-item small {
  margin: 0;
}

.detail-mobile__cta {
  position: sticky;
  bottom: 84px;
  z-index: 18;
}

.detail-desktop {
  display: grid;
  grid-template-columns: 340px minmax(0, 1fr);
  gap: 16px;
}

.section-head {
  margin-bottom: 12px;
}

.section-head h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
}

@media (max-width: 1024px) {
  .detail-desktop {
    grid-template-columns: 1fr;
  }
}
</style>
