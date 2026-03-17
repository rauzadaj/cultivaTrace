<template>
  <div class="plant-list">
    <header class="plant-list__header">
      <div>
        <p class="section-eyebrow">Plants</p>
        <h1>Liste terrain</h1>
      </div>
      <div v-if="!isMobile" class="plant-list__actions">
        <q-btn-toggle
          v-model="desktopMode"
          no-caps
          unelevated
          toggle-color="primary"
          :options="modeOptions"
        />
      </div>
    </header>

    <q-pull-to-refresh v-if="isMobile" @refresh="refreshPlants">
      <div class="filter-bar">
        <q-chip
          v-for="filter in stageFilters"
          :key="filter.id"
          clickable
          :outline="activeStage !== filter.value"
          :color="activeStage === filter.value ? 'primary' : 'grey-7'"
          :text-color="activeStage === filter.value ? 'white' : 'dark'"
          @click="activeStage = filter.value"
        >
          {{ filter.label }}
        </q-chip>
      </div>

      <div v-if="cropStore.loading" class="skeleton-list">
        <q-skeleton v-for="index in 5" :key="index" height="72px" class="skeleton-list__item" />
      </div>

      <div v-else-if="filteredPlants.length" class="mobile-list">
        <q-slide-item
          v-for="plant in filteredPlants"
          :key="plant.id"
          right-color="primary"
          @right="openStageChange"
        >
          <template #right>
            <div class="slide-action">Changer stade</div>
          </template>

          <c-card class="mobile-list__item" @click="openPlant(plant.id)">
            <div class="mobile-list__content">
              <div>
                <strong>{{ plant.name }}</strong>
                <p>{{ plant.strain }}</p>
              </div>
              <plant-stage-chip :stage="plant.stage" />
              <div class="mobile-list__meta">
                <span>{{ plant.ageInDays }} j</span>
                <span>{{ plant.room }}</span>
              </div>
            </div>
          </c-card>
        </q-slide-item>
      </div>

      <div v-else class="empty-block">
        <q-icon name="mdi-sprout-outline" size="32px" />
        <strong>Aucun plant actif</strong>
        <span>Creez votre premier plant ou modifiez les filtres.</span>
        <c-btn variant="primary">Creer un plant</c-btn>
      </div>
    </q-pull-to-refresh>

    <div v-else class="desktop-shell">
      <aside class="desktop-sidebar">
        <c-card>
          <q-expansion-item default-opened label="Filtres" header-class="desktop-sidebar__title">
            <div class="desktop-sidebar__filters">
              <q-chip
                v-for="filter in stageFilters"
                :key="filter.id"
                clickable
                :outline="activeStage !== filter.value"
                :color="activeStage === filter.value ? 'primary' : 'grey-7'"
                :text-color="activeStage === filter.value ? 'white' : 'dark'"
                @click="activeStage = filter.value"
              >
                {{ filter.label }}
              </q-chip>
            </div>
          </q-expansion-item>
        </c-card>
      </aside>

      <section class="desktop-content">
        <div v-if="desktopMode === 'list'" class="desktop-list">
          <c-card v-for="plant in filteredPlants" :key="plant.id" class="desktop-list__item" @click="openPlant(plant.id)">
            <div class="desktop-list__content">
              <div>
                <strong>{{ plant.name }}</strong>
                <p>{{ plant.strain }}</p>
              </div>
              <plant-stage-chip :stage="plant.stage" />
              <span>{{ plant.ageInDays }} j</span>
              <span>{{ plant.room }}</span>
            </div>
          </c-card>
        </div>

        <div v-else class="desktop-grid">
          <c-card v-for="plant in filteredPlants" :key="plant.id" class="desktop-grid__item" @click="openPlant(plant.id)">
            <plant-stage-chip :stage="plant.stage" />
            <strong>{{ plant.name }}</strong>
            <p>{{ plant.strain }}</p>
            <small>{{ plant.room }}</small>
          </c-card>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import CBtn from '../../components/ui/CBtn.vue'
import CCard from '../../components/ui/CCard.vue'
import PlantStageChip from '../../components/ui/PlantStageChip.vue'
import { useDisplay } from '../../composables/useDisplay'
import { useCropStore } from '../../stores/useCropStore'
import type { PlantCardSummary } from '../../types/api'

const router = useRouter()
const cropStore = useCropStore()
const { xs } = useDisplay()

const isMobile = computed(() => xs.value)
const activeStage = ref<'all' | 'seedling' | 'veg' | 'flower' | 'harvest'>('all')
const desktopMode = ref<'list' | 'grid'>('list')

const modeOptions = [
  { label: 'Liste', value: 'list' },
  { label: 'Grille', value: 'grid' },
] as const

const stageFilters = [
  { id: 'all', label: 'Tous', value: 'all' },
  { id: 'seedling', label: 'Seedling', value: 'seedling' },
  { id: 'veg', label: 'Veg', value: 'veg' },
  { id: 'flower', label: 'Flower', value: 'flower' },
  { id: 'harvest', label: 'Harvest', value: 'harvest' },
] as const

const filteredPlants = computed<PlantCardSummary[]>(() => cropStore.crops
  .filter((crop) => activeStage.value === 'all' || crop.currentStage === activeStage.value)
  .map((crop) => ({
    id: crop.id,
    name: crop.displayName,
    strain: crop.genetic.name,
    room: crop.batchCode,
    stage: crop.currentStage,
    ageInDays: Math.max(1, Math.round((Date.now() - new Date(crop.seededAt).getTime()) / 86_400_000)),
  })))

async function refreshPlants(done: () => void) {
  await cropStore.loadDashboard()
  done()
}

function openPlant(id: string) {
  void router.push({ name: 'plant-detail', params: { id } })
}

function openStageChange(_details: { reset: () => void }) {
  // UX scaffold only; actual mutation stays in dedicated flows.
}
</script>

<style scoped>
.plant-list {
  display: grid;
  gap: 16px;
}

.plant-list__header,
.plant-list__actions {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}

.plant-list__header h1 {
  margin: 0;
  font-size: 2rem;
  font-weight: 600;
}

.section-eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.filter-bar {
  position: sticky;
  top: 0;
  z-index: 10;
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 4px 0 12px;
  background: #f7f8fa;
}

.mobile-list {
  display: grid;
  gap: 12px;
}

.mobile-list__item {
  min-height: 72px;
  cursor: pointer;
}

.mobile-list__content {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px 12px;
  align-items: center;
}

.mobile-list__content strong,
.mobile-list__content p,
.mobile-list__content span {
  margin: 0;
}

.mobile-list__meta {
  display: flex;
  gap: 12px;
  color: #718096;
  font-size: 0.875rem;
  grid-column: 1 / -1;
}

.slide-action {
  min-width: 124px;
  min-height: 72px;
  display: grid;
  place-items: center;
  color: #fff;
  font-weight: 600;
}

.skeleton-list {
  display: grid;
  gap: 12px;
}

.skeleton-list__item {
  border-radius: 12px;
}

.empty-block {
  display: grid;
  justify-items: start;
  gap: 10px;
  padding: 20px;
  border-radius: 12px;
  background: #fff;
}

.desktop-shell {
  display: grid;
  grid-template-columns: 280px minmax(0, 1fr);
  gap: 16px;
}

.desktop-sidebar__filters {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.desktop-list,
.desktop-grid {
  display: grid;
  gap: 12px;
}

.desktop-list__item,
.desktop-grid__item {
  cursor: pointer;
}

.desktop-list__content {
  display: grid;
  grid-template-columns: minmax(0, 1.3fr) auto 72px 120px;
  gap: 12px;
  align-items: center;
}

.desktop-grid {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.desktop-grid__item {
  display: grid;
  gap: 10px;
}

@media (max-width: 1024px) {
  .desktop-shell {
    grid-template-columns: 1fr;
  }
}
</style>
