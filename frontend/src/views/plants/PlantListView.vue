<template>
  <div class="plant-list">
    <header class="plant-list__header">
      <div>
        <p class="section-eyebrow">Plants</p>
        <h1>Liste terrain</h1>
      </div>
      <div class="plant-list__actions">
        <c-btn variant="primary" @click="createPlantOpen = true">Nouveau plant</c-btn>
        <q-btn-toggle
          v-if="!isMobile"
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

      <div v-if="plantsStore.loading" class="skeleton-list">
        <q-skeleton v-for="index in 5" :key="index" height="72px" class="skeleton-list__item" />
      </div>

      <div v-else-if="filteredPlants.length" class="mobile-list">
        <c-card v-for="plant in filteredPlants" :key="plant.id" class="mobile-list__item" @click="openPlant(plant.id)">
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
      </div>

      <div v-else class="empty-block">
        <q-icon name="mdi-sprout-outline" size="32px" />
        <strong>Aucun plant actif</strong>
        <span>Creez votre premier plant ou modifiez les filtres.</span>
      </div>
    </q-pull-to-refresh>

    <div v-else class="desktop-shell">
      <section class="desktop-content">
        <c-card class="desktop-filters">
          <div class="desktop-filters__row">
            <span class="desktop-filters__label">Filtres</span>
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
          </div>
        </c-card>

        <div v-if="plantsStore.loading" class="desktop-skeleton">
          <q-skeleton v-for="index in 4" :key="index" height="108px" class="skeleton-list__item" />
        </div>

        <div v-else-if="!filteredPlants.length" class="empty-block">
          <q-icon name="mdi-sprout-outline" size="32px" />
          <strong>Aucun plant pour ce filtre</strong>
          <span>Ajustez les filtres ou creez un nouveau plant.</span>
          <c-btn variant="secondary" @click="activeStage = 'all'">Reinitialiser les filtres</c-btn>
        </div>

        <div v-else-if="desktopMode === 'list'" class="desktop-list">
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

    <plant-form v-model="createPlantOpen" @created="handlePlantCreated" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import PlantForm from '../../components/plants/PlantForm.vue'
import CBtn from '../../components/ui/CBtn.vue'
import CCard from '../../components/ui/CCard.vue'
import PlantStageChip from '../../components/ui/PlantStageChip.vue'
import { useDisplay } from '../../composables/useDisplay'
import { usePlantsStore } from '../../stores/plants'
import type { PlantCardSummary, PlantStage } from '../../types/api'

const router = useRouter()
const plantsStore = usePlantsStore()
const { xs } = useDisplay()

const isMobile = computed(() => xs.value)
const activeStage = ref<'all' | PlantStage>('all')
const desktopMode = ref<'list' | 'grid'>('list')
const createPlantOpen = ref(false)

const modeOptions = [
  { label: 'Liste', value: 'list' },
  { label: 'Grille', value: 'grid' },
] as const

const stageFilters = [
  { id: 'all', label: 'Tous', value: 'all' },
  { id: 'germination', label: 'Germination', value: 'germination' },
  { id: 'vegetation', label: 'Vegetation', value: 'vegetation' },
  { id: 'flowering', label: 'Floraison', value: 'flowering' },
  { id: 'harvest', label: 'Recolte', value: 'harvest' },
  { id: 'archived', label: 'Archive', value: 'archived' },
] as const

const filteredPlants = computed<PlantCardSummary[]>(() => plantsStore.plantCards
  .filter((plant) => activeStage.value === 'all' || plant.stage === activeStage.value))

onMounted(async () => {
  if (!plantsStore.plants.length) {
    try {
      await plantsStore.bootstrap()
    } catch (error) {
      console.error('Plant list bootstrap failed', error)
    }
  }
})

async function refreshPlants(done: () => void) {
  try {
    await plantsStore.fetchPlants()
  } catch (error) {
    console.error('Plant list refresh failed', error)
  }
  done()
}

function openPlant(id: string) {
  void router.push({ name: 'plant-detail', params: { id } })
}

function handlePlantCreated(id: string) {
  createPlantOpen.value = false
  void router.push({ name: 'plant-detail', params: { id } })
}
</script>

<style scoped lang="scss">
@use '../../css/breakpoints.sass' as bp;
.plant-list { display: grid; gap: 16px; min-width: 0; }
.plant-list__header, .plant-list__actions { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.plant-list__header h1 { margin: 0; font-size: 1.75rem; font-weight: 600; }
.section-eyebrow { margin: 0 0 6px; color: #718096; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.12em; }
.filter-bar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; gap: 8px; padding: 4px 0 12px; background: #f7f8fa; }
.mobile-list { display: grid; gap: 12px; }
.mobile-list__item { min-height: 72px; cursor: pointer; }
.mobile-list__content { display: grid; grid-template-columns: 1fr; gap: 10px 12px; align-items: center; }
.mobile-list__content strong, .mobile-list__content p, .mobile-list__content span { margin: 0; }
.mobile-list__content p, .mobile-list__meta { color: #718096; }
.mobile-list__meta { display: flex; justify-content: space-between; gap: 12px; }
.desktop-shell, .desktop-content, .desktop-list, .desktop-grid, .desktop-skeleton { display: grid; gap: 16px; }
.desktop-filters__row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
.desktop-filters__label { font-size: 0.875rem; font-weight: 600; color: #4a5568; }
.desktop-sidebar__filters { display: flex; flex-wrap: wrap; gap: 8px; }
.desktop-list__content { display: grid; grid-template-columns: minmax(0, 2fr) auto auto auto; gap: 16px; align-items: center; }
.desktop-list__content p, .desktop-grid p, .desktop-grid small { margin: 0; color: #718096; }
.desktop-grid { grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); }
.desktop-grid__item { display: grid; gap: 10px; }
.empty-block { display: grid; justify-items: start; gap: 10px; padding: 16px 0; }
.skeleton-list__item { border-radius: 16px; }
@include bp.mobile { .plant-list__header h1 { font-size: 1.75rem; } }
</style>
