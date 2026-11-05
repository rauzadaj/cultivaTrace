<template>
  <section class="cultivation-overview" aria-label="Vue d’ensemble des salles de culture">
    <header class="cultivation-overview__header">
      <div class="cultivation-overview__heading">
        <div class="cultivation-overview__eyebrow-row">
          <span class="cultivation-overview__eyebrow">SUPERVISION &amp; CULTURE</span>
          <span class="cultivation-overview__separator">/</span>
          <span class="cultivation-overview__context">Vue des installations</span>
        </div>
        <h1>Salles de culture</h1>
        <p>Capacité, occupation et répartition des plants dans vos installations.</p>
      </div>
      <q-btn
        color="primary"
        icon="mdi-door-plus"
        label="Nouvelle salle"
        no-caps
        class="cultivation-overview__create"
        @click="$emit('createRoom')"
      />
    </header>

    <div class="cultivation-overview__metrics" aria-label="Indicateurs des installations">
      <q-card flat class="cultivation-overview__metric">
        <div class="cultivation-overview__metric-head">
          <div>
            <p class="cultivation-overview__metric-label">Emprise &amp; occupation</p>
            <p class="cultivation-overview__metric-caption">Capacité cumulée des salles</p>
          </div>
          <div
            v-if="!roomsLoading && !roomsError"
            class="cultivation-overview__gauge"
            role="progressbar"
            aria-label="Occupation des salles"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="occupancyPercent ?? undefined"
            :style="{ '--room-occupancy': `${occupancyPercent ?? 0}%` }"
          >
            <strong>{{ occupancyPercent === null ? '—' : `${occupancyPercent}%` }}</strong>
          </div>
        </div>
        <div v-if="roomsLoading" class="cultivation-overview__loading" role="status">
          <q-spinner color="primary" size="18px" /> Chargement de la capacité…
        </div>
        <p v-else-if="roomsError" class="cultivation-overview__metric-footnote" role="status">
          Capacité indisponible.
        </p>
        <template v-else>
          <div class="cultivation-overview__metric-value">
            <strong>{{ occupiedPlants === null ? '—' : occupiedPlants }}</strong>
            <span>/ {{ totalCapacity }} places</span>
          </div>
          <div v-if="occupancyPercent !== null" class="cultivation-overview__progress" aria-hidden="true">
            <span :style="{ width: `${occupancyPercent}%` }" />
          </div>
          <p class="cultivation-overview__metric-footnote">
            {{ occupancyUnavailable ? 'Le nombre de plants actifs est indisponible pour une ou plusieurs salles.' : `${rooms.length} ${rooms.length === 1 ? 'salle configurée' : 'salles configurées'}` }}
          </p>
        </template>
      </q-card>

      <q-card flat class="cultivation-overview__metric">
        <div class="cultivation-overview__metric-head">
          <div>
            <p class="cultivation-overview__metric-label">Répartition opérationnelle</p>
            <p class="cultivation-overview__metric-caption">Plants par stade de culture</p>
          </div>
          <q-icon name="mdi-sprout-outline" size="20px" class="cultivation-overview__icon" aria-hidden="true" />
        </div>
        <div v-if="overviewLoading" class="cultivation-overview__loading" role="status">
          <q-spinner color="primary" size="18px" /> Chargement des indicateurs…
        </div>
        <p v-else-if="overviewError" class="cultivation-overview__metric-footnote" role="status">
          Répartition indisponible.
        </p>
        <div v-else-if="stageBreakdown.length" class="cultivation-overview__stages">
          <div v-for="stage in stageBreakdown" :key="stage.id" class="cultivation-overview__stage">
            <strong>{{ stage.value }}</strong>
            <span>{{ stage.label }}</span>
          </div>
        </div>
        <p v-else class="cultivation-overview__metric-footnote">Aucun plant actif recensé.</p>
        <p class="cultivation-overview__metric-footnote">
          {{ overviewError ? 'Les données du tableau de bord ne sont pas disponibles.' : `${overview?.plants.total ?? 0} plants actifs recensés par l’API` }}
        </p>
      </q-card>
    </div>

    <section class="cultivation-overview__rooms" aria-label="Liste des salles">
      <header class="cultivation-overview__rooms-heading">
        <div>
          <p class="cultivation-overview__eyebrow">INSTALLATIONS</p>
          <h2>Vos salles</h2>
        </div>
        <span class="cultivation-overview__room-count">{{ filteredRooms.length }} / {{ rooms.length }}</span>
      </header>

      <div class="cultivation-overview__toolbar">
        <div class="cultivation-overview__filters" role="group" aria-label="Filtrer par type de salle">
          <button
            v-for="filter in roomFilters"
            :key="filter.value"
            type="button"
            class="cultivation-overview__filter"
            :class="{ 'cultivation-overview__filter--active': activeFilter === filter.value }"
            :aria-pressed="activeFilter === filter.value"
            @click="activeFilter = filter.value"
          >
            {{ filter.label }} <span>{{ filter.count }}</span>
          </button>
        </div>

        <div class="cultivation-overview__tools">
          <label class="cultivation-overview__search">
            <q-icon name="mdi-magnify" size="17px" aria-hidden="true" />
            <input v-model="search" type="search" aria-label="Rechercher une salle" placeholder="Rechercher une salle…">
          </label>
          <q-btn-toggle
            v-model="presentation"
            class="cultivation-overview__view-toggle"
            no-caps
            unelevated
            toggle-color="primary"
            :options="[
              { value: 'grid', slot: 'grid', attrs: { 'aria-label': 'Cartes' } },
              { value: 'list', slot: 'list', attrs: { 'aria-label': 'Liste' } },
            ]"
            aria-label="Mode de présentation des salles"
          >
            <template #grid><q-icon name="mdi-view-grid-outline" size="16px" aria-label="Cartes" /></template>
            <template #list><q-icon name="mdi-view-list-outline" size="17px" aria-label="Liste" /></template>
          </q-btn-toggle>
        </div>
      </div>

      <div v-if="roomsLoading" class="cultivation-overview__room-grid" aria-label="Chargement des salles" aria-busy="true">
        <q-skeleton v-for="item in 3" :key="item" height="184px" class="cultivation-overview__skeleton" />
      </div>
      <div v-else-if="roomsError" class="cultivation-overview__empty" role="alert">
        <q-icon name="mdi-alert-circle-outline" size="22px" />
        <span>Impossible de charger les salles.</span>
      </div>
      <div v-else-if="filteredRooms.length === 0" class="cultivation-overview__empty" role="status">
        <q-icon name="mdi-door-open" size="22px" />
        <span>{{ rooms.length ? 'Aucune salle ne correspond à ces filtres.' : 'Aucune salle configurée.' }}</span>
      </div>
      <div v-else class="cultivation-overview__room-grid" :class="`cultivation-overview__room-grid--${presentation}`">
        <q-card v-for="room in filteredRooms" :key="room.id" flat class="cultivation-overview__room-card">
          <div class="cultivation-overview__room-top">
            <div class="cultivation-overview__room-icon"><q-icon name="mdi-door" size="18px" /></div>
            <span class="cultivation-overview__room-type">{{ roomTypeLabel(room.type) }}</span>
          </div>
          <h3>{{ room.name }}</h3>
          <p v-if="farmName(room)" class="cultivation-overview__room-farm">{{ farmName(room) }}</p>
          <div class="cultivation-overview__room-capacity">
            <span><strong>{{ room.activePlantCount ?? '—' }}</strong> plants actifs</span>
            <span>Capacité {{ room.capacityMax }}</span>
          </div>
          <div
            v-if="roomOccupancy(room) !== null"
            class="cultivation-overview__room-progress"
            role="progressbar"
            :aria-label="`Occupation de ${room.name}`"
            aria-valuemin="0"
            aria-valuemax="100"
            :aria-valuenow="roomOccupancy(room) ?? undefined"
          >
            <span :style="{ width: `${roomOccupancy(room)}%` }" />
          </div>
          <footer class="cultivation-overview__room-footer">
            <span>{{ roomOccupancy(room) === null ? 'Occupation indisponible' : `${roomOccupancy(room)} % occupée` }}</span>
            <q-btn flat no-caps color="primary" label="Ouvrir" :aria-label="`Ouvrir la salle ${room.name}`" @click="$emit('openRoom', room.id)" />
          </footer>
        </q-card>
      </div>
    </section>

    <section v-if="recentEvents.length" class="cultivation-overview__activity" aria-label="Activité récente">
      <header class="cultivation-overview__rooms-heading">
        <div>
          <p class="cultivation-overview__eyebrow">TRAÇABILITÉ</p>
          <h2>Activité récente</h2>
        </div>
        <RouterLink :to="{ name: 'culture-journal' }" class="cultivation-overview__journal-link">Ouvrir le journal</RouterLink>
      </header>
      <div class="cultivation-overview__activity-list">
        <article v-for="event in recentEvents" :key="event.id" class="cultivation-overview__activity-row">
          <span class="cultivation-overview__activity-type">{{ eventTypeLabel(event.eventType) }}</span>
          <p>{{ event.notes || 'Événement consigné dans le journal.' }}</p>
          <time :datetime="event.occurredAt">{{ formatEventDate(event.occurredAt) }}</time>
        </article>
      </div>
    </section>
  </section>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import type { DashboardOverviewResponse, DashboardRecentEvent, Room, RoomType } from '@/types/api'

const props = defineProps<{
  rooms: Room[]
  overview: DashboardOverviewResponse | null
  roomsLoading: boolean
  roomsError: boolean
  overviewLoading: boolean
  overviewError: boolean
}>()

defineEmits<{
  createRoom: []
  openRoom: [roomId: string]
}>()

const activeFilter = ref<'all' | RoomType>('all')
const search = ref('')
const presentation = ref<'grid' | 'list'>('grid')

const roomTypeOptions: { value: RoomType; label: string }[] = [
  { value: 'veg', label: 'Végétation' },
  { value: 'flower', label: 'Floraison' },
  { value: 'clone', label: 'Clonage' },
  { value: 'drying', label: 'Séchage' },
  { value: 'mixed', label: 'Polyvalente' },
]

const roomFilters = computed(() => [
  { value: 'all' as const, label: 'Toutes', count: props.rooms.length },
  ...roomTypeOptions.map((option) => ({
    ...option,
    count: props.rooms.filter((room) => room.type === option.value).length,
  })),
])

const filteredRooms = computed(() => props.rooms.filter((room) => {
  const matchesType = activeFilter.value === 'all' || room.type === activeFilter.value
  const matchesSearch = !search.value.trim() || room.name.toLocaleLowerCase('fr-FR').includes(search.value.trim().toLocaleLowerCase('fr-FR'))
  return matchesType && matchesSearch
}))

const roomsLoading = computed(() => props.roomsLoading)
const roomsError = computed(() => props.roomsError)
const occupancyUnavailable = computed(() => props.rooms.some((room) => typeof room.activePlantCount !== 'number'))
const occupiedPlants = computed<number | null>(() => occupancyUnavailable.value
  ? null
  : props.rooms.reduce((total, room) => total + (room.activePlantCount ?? 0), 0))
const totalCapacity = computed(() => props.rooms.reduce((total, room) => total + Math.max(0, room.capacityMax), 0))
const occupancyPercent = computed<number | null>(() => {
  if (occupiedPlants.value === null || totalCapacity.value <= 0) return null
  return Math.min(100, Math.max(0, Math.round(occupiedPlants.value / totalCapacity.value * 100)))
})

const stageNames: { id: keyof DashboardOverviewResponse['plants']['byStage']; label: string }[] = [
  { id: 'germination', label: 'Germination' },
  { id: 'vegetation', label: 'Végétation' },
  { id: 'flowering', label: 'Floraison' },
  { id: 'harvest', label: 'Récolte' },
]
const stageBreakdown = computed(() => {
  const stages = props.overview?.plants.byStage
  if (!stages) return []
  return stageNames.map((stage) => ({ id: stage.id, label: stage.label, value: stages[stage.id] ?? 0 }))
})
const recentEvents = computed<DashboardRecentEvent[]>(() => props.overview?.overview.recentEvents ?? [])

function roomTypeLabel(type: RoomType): string {
  return roomTypeOptions.find((option) => option.value === type)?.label ?? type
}

function roomOccupancy(room: Room): number | null {
  if (typeof room.occupancyRate === 'number') return Math.min(100, Math.max(0, Math.round(room.occupancyRate)))
  if (typeof room.activePlantCount !== 'number' || room.capacityMax <= 0) return null
  return Math.min(100, Math.max(0, Math.round(room.activePlantCount / room.capacityMax * 100)))
}

function farmName(room: Room): string | null {
  return typeof room.farm === 'string' ? null : room.farm.name
}

function eventTypeLabel(eventType: DashboardRecentEvent['eventType']): string {
  const labels: Record<string, string> = {
    stage_change: 'Transition',
    room_move: 'Déplacement',
    input_record: 'Intrant',
    harvest: 'Récolte',
    note: 'Note',
  }
  return labels[eventType] ?? 'Événement'
}

function formatEventDate(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return 'Date indisponible'
  return date.toLocaleString('fr-FR', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>

<style scoped>
.cultivation-overview { display: grid; grid-template-columns: minmax(0, 1fr); width: 100%; min-width: 0; max-width: 100%; box-sizing: border-box; gap: var(--ct-space-5); padding-bottom: calc(var(--ct-space-6) + env(safe-area-inset-bottom)); color: var(--ct-text); }
.cultivation-overview__header { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--ct-space-4); min-height: 175px; }
.cultivation-overview__heading { width: 100%; min-width: 0; }
.cultivation-overview__eyebrow-row { display: flex; align-items: center; gap: var(--ct-space-2); margin-bottom: var(--ct-space-3); }
.cultivation-overview__eyebrow { margin: 0; color: var(--ct-mint); font: 600 10px/var(--ct-line-sm) var(--ct-font-sans); letter-spacing: .08em; }
.cultivation-overview__separator, .cultivation-overview__context { color: var(--ct-muted); font: 11px/var(--ct-line-sm) var(--ct-font-serif); }
.cultivation-overview__header h1 { margin: 0; color: var(--ct-text); font: 600 34px/1.12 var(--ct-font-sans); letter-spacing: var(--ct-tracking-heading); }
.cultivation-overview__header p:not(.cultivation-overview__eyebrow) { width: 100%; max-width: 560px; margin: var(--ct-space-3) 0 0; color: var(--ct-muted); font: 14px/1.5 var(--ct-font-serif); white-space: normal; overflow-wrap: anywhere; }
.cultivation-overview__create { min-height: 38px; border-radius: var(--ct-radius-sm); font: 500 12px/1 var(--ct-font-sans); }
.cultivation-overview__metrics { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--ct-space-3); }
.cultivation-overview__metric { display: grid; align-content: start; gap: var(--ct-space-3); min-width: 0; min-height: 166px; padding: var(--ct-space-4); border: 1px solid var(--ct-border-strong); border-radius: var(--ct-radius-md); background: var(--ct-surface); color: var(--ct-text); }
.cultivation-overview__metric-head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--ct-space-2); }
.cultivation-overview__metric-label { margin: 0; color: var(--ct-secondary); font: 500 var(--ct-text-body)/var(--ct-line-sm) var(--ct-font-sans); }
.cultivation-overview__metric-caption, .cultivation-overview__metric-footnote { margin: var(--ct-space-1) 0 0; color: var(--ct-muted); font: 11px/var(--ct-line-sm) var(--ct-font-serif); }
.cultivation-overview__metric-value { display: flex; align-items: baseline; gap: var(--ct-space-2); }
.cultivation-overview__metric-value strong { font: 700 26px/1.2 var(--ct-font-sans); letter-spacing: var(--ct-tracking-heading); }
.cultivation-overview__metric-value span { color: var(--ct-muted); font: 12px/var(--ct-line-sm) var(--ct-font-serif); }
.cultivation-overview__gauge { position: relative; display: grid; width: 56px; height: 56px; flex: 0 0 56px; place-items: center; border-radius: 50%; background: conic-gradient(var(--ct-mint) var(--room-occupancy), var(--ct-raised) 0); }
.cultivation-overview__gauge::before { position: absolute; width: 44px; height: 44px; border-radius: 50%; background: var(--ct-surface); content: ''; }
.cultivation-overview__gauge strong { z-index: 1; font: 600 11px/1 var(--ct-font-sans); }
.cultivation-overview__progress, .cultivation-overview__room-progress { height: 4px; overflow: hidden; border-radius: 2px; background: var(--ct-raised); }
.cultivation-overview__progress span, .cultivation-overview__room-progress span { display: block; height: 100%; border-radius: inherit; background: var(--ct-mint); transition: width 160ms ease; }
.cultivation-overview__stages { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--ct-space-2); }
.cultivation-overview__stage { display: grid; gap: var(--ct-space-2); min-width: 0; padding: var(--ct-space-2); background: var(--ct-inset); }
.cultivation-overview__stage strong { font: 600 19px/1.2 var(--ct-font-sans); }
.cultivation-overview__stage span { overflow: hidden; color: var(--ct-muted); font: 10px/var(--ct-line-xs) var(--ct-font-serif); text-overflow: ellipsis; white-space: nowrap; }
.cultivation-overview__icon { color: var(--ct-mint); }
.cultivation-overview__loading { display: flex; align-items: center; gap: var(--ct-space-2); color: var(--ct-muted); font: 12px/1.4 var(--ct-font-serif); }
.cultivation-overview__rooms, .cultivation-overview__activity { display: grid; gap: var(--ct-space-3); }
.cultivation-overview__rooms-heading { display: flex; align-items: flex-end; justify-content: space-between; gap: var(--ct-space-3); }
.cultivation-overview__rooms-heading h2 { margin: var(--ct-space-1) 0 0; font: 600 20px/1.2 var(--ct-font-sans); letter-spacing: var(--ct-tracking-heading); }
.cultivation-overview__room-count { color: var(--ct-muted); font: 11px/1.4 var(--ct-font-serif); }
.cultivation-overview__toolbar { display: flex; align-items: center; justify-content: space-between; gap: var(--ct-space-3); min-height: 44px; }
.cultivation-overview__filters { display: flex; align-items: center; gap: 3px; max-width: 100%; overflow-x: auto; padding: 3px; border: 1px solid var(--ct-border); border-radius: var(--ct-radius-sm); background: var(--ct-inset); scrollbar-width: thin; }
.cultivation-overview__filter { display: inline-flex; flex: 0 0 auto; align-items: center; gap: 7px; min-height: 28px; padding: 0 10px; border: 0; border-radius: 4px; background: transparent; color: var(--ct-muted); cursor: pointer; font: 500 11px/1 var(--ct-font-sans); }
.cultivation-overview__filter span { color: inherit; font: 10px/1 var(--ct-font-sans); opacity: .8; }
.cultivation-overview__filter--active { background: var(--ct-raised); color: var(--ct-text); }
.cultivation-overview__tools { display: flex; align-items: center; gap: var(--ct-space-2); }
.cultivation-overview__search { display: flex; align-items: center; gap: var(--ct-space-2); width: 210px; min-height: 34px; padding: 0 10px; border: 1px solid var(--ct-border-strong); border-radius: var(--ct-radius-sm); color: var(--ct-muted); }
.cultivation-overview__search input { width: 100%; min-width: 0; border: 0; outline: 0; background: transparent; color: var(--ct-text); font: 11px/1.4 var(--ct-font-sans); }
.cultivation-overview__search input::placeholder { color: var(--ct-muted); }
.cultivation-overview__view-toggle { min-height: 34px; padding: 3px; border: 1px solid var(--ct-border-strong); border-radius: var(--ct-radius-sm); background: var(--ct-inset); }
.cultivation-overview__view-toggle :deep(.q-btn) { min-width: 30px; min-height: 26px; padding: 0 7px; border-radius: 4px; color: var(--ct-muted); }
.cultivation-overview__view-toggle :deep(.q-btn--active) { color: var(--ct-on-primary); }
.cultivation-overview__room-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: var(--ct-space-3); }
.cultivation-overview__room-grid--list { grid-template-columns: 1fr; }
.cultivation-overview__room-card { display: grid; align-content: start; gap: var(--ct-space-3); min-width: 0; min-height: 184px; padding: var(--ct-space-4); border: 1px solid var(--ct-border-strong); border-radius: var(--ct-radius-md); background: var(--ct-surface); color: var(--ct-text); }
.cultivation-overview__room-grid--list .cultivation-overview__room-card { grid-template-columns: minmax(120px, 1.5fr) minmax(120px, .8fr) minmax(160px, 1fr) minmax(110px, .8fr); align-items: center; min-height: 80px; }
.cultivation-overview__room-grid--list .cultivation-overview__room-top { grid-column: 1; grid-row: 1; }
.cultivation-overview__room-grid--list .cultivation-overview__room-card h3 { grid-column: 1; grid-row: 2; margin-top: -10px; }
.cultivation-overview__room-grid--list .cultivation-overview__room-farm { display: none; }
.cultivation-overview__room-grid--list .cultivation-overview__room-capacity { grid-column: 2; grid-row: 1 / span 2; }
.cultivation-overview__room-grid--list .cultivation-overview__room-progress { grid-column: 3; grid-row: 1; }
.cultivation-overview__room-grid--list .cultivation-overview__room-footer { grid-column: 4; grid-row: 1 / span 2; }
.cultivation-overview__room-top { display: flex; align-items: center; justify-content: space-between; gap: var(--ct-space-2); }
.cultivation-overview__room-icon { display: grid; width: 34px; height: 34px; place-items: center; border: 1px solid var(--ct-border); border-radius: 8px; background: var(--ct-inset); color: var(--ct-mint); }
.cultivation-overview__room-type { padding: 4px 8px; border: 1px solid var(--ct-border); border-radius: 999px; color: var(--ct-secondary); font: 10px/1.2 var(--ct-font-sans); }
.cultivation-overview__room-card h3 { overflow: hidden; margin: 0; font: 600 16px/1.25 var(--ct-font-sans); text-overflow: ellipsis; white-space: nowrap; }
.cultivation-overview__room-farm { overflow: hidden; margin: -8px 0 0; color: var(--ct-muted); font: 11px/1.3 var(--ct-font-serif); text-overflow: ellipsis; white-space: nowrap; }
.cultivation-overview__room-capacity { display: flex; align-items: baseline; justify-content: space-between; gap: var(--ct-space-2); color: var(--ct-muted); font: 11px/1.4 var(--ct-font-serif); }
.cultivation-overview__room-capacity strong { color: var(--ct-text); font: 600 14px/1.2 var(--ct-font-sans); }
.cultivation-overview__room-footer { display: flex; align-items: center; justify-content: space-between; gap: var(--ct-space-2); margin-top: -8px; color: var(--ct-muted); font: 10px/1.2 var(--ct-font-serif); }
.cultivation-overview__room-footer :deep(.q-btn) { min-height: 28px; padding: 0 4px; font: 500 11px/1 var(--ct-font-sans); }
.cultivation-overview__empty { display: flex; align-items: center; gap: var(--ct-space-2); min-height: 96px; padding: var(--ct-space-4); border: 1px solid var(--ct-border); border-radius: var(--ct-radius-md); color: var(--ct-muted); font: 12px/1.4 var(--ct-font-serif); }
.cultivation-overview__skeleton { border-radius: var(--ct-radius-md); }
.cultivation-overview__activity-list { overflow: hidden; border: 1px solid var(--ct-border-strong); border-radius: var(--ct-radius-md); background: var(--ct-surface); }
.cultivation-overview__activity-row { display: grid; grid-template-columns: 120px minmax(0, 1fr) 120px; align-items: center; gap: var(--ct-space-3); min-height: 46px; padding: 0 var(--ct-space-3); border-bottom: 1px solid var(--ct-border); }
.cultivation-overview__activity-row:last-child { border-bottom: 0; }
.cultivation-overview__activity-type { color: var(--ct-mint); font: 500 10px/1.3 var(--ct-font-sans); }
.cultivation-overview__activity-row p { overflow: hidden; margin: 0; color: var(--ct-secondary); font: 11px/1.4 var(--ct-font-serif); text-overflow: ellipsis; white-space: nowrap; }
.cultivation-overview__activity-row time { color: var(--ct-muted); font: 10px/1.3 var(--ct-font-serif); text-align: right; }
.cultivation-overview__journal-link { color: var(--ct-mint); font: 11px/1.3 var(--ct-font-sans); text-decoration: none; }
.cultivation-overview__journal-link:hover { text-decoration: underline; }
@media (max-width: 900px) {
  .cultivation-overview__room-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .cultivation-overview__toolbar { align-items: flex-start; flex-direction: column; }
  .cultivation-overview__filters { width: 100%; }
  .cultivation-overview__tools { width: 100%; justify-content: space-between; }
}
@media (max-width: 640px) {
  .cultivation-overview { gap: var(--ct-space-4); }
  .cultivation-overview__header { align-items: flex-start; flex-direction: column; justify-content: center; min-height: 150px; }
  .cultivation-overview__header h1 { font-size: 28px; }
  .cultivation-overview__metrics, .cultivation-overview__room-grid { grid-template-columns: minmax(0, 1fr); }
  .cultivation-overview__stages { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .cultivation-overview__tools { align-items: stretch; }
  .cultivation-overview__search { width: 100%; }
  .cultivation-overview__room-grid--list .cultivation-overview__room-card { grid-template-columns: 1fr auto; }
  .cultivation-overview__room-grid--list .cultivation-overview__room-top,
  .cultivation-overview__room-grid--list .cultivation-overview__room-card h3 { grid-column: 1; grid-row: auto; }
  .cultivation-overview__room-grid--list .cultivation-overview__room-capacity { grid-column: 1 / span 2; grid-row: auto; }
  .cultivation-overview__room-grid--list .cultivation-overview__room-progress { grid-column: 1 / span 2; grid-row: auto; }
  .cultivation-overview__room-grid--list .cultivation-overview__room-footer { grid-column: 2; grid-row: 1 / span 2; }
  .cultivation-overview__activity-row { grid-template-columns: 1fr auto; gap: var(--ct-space-1) var(--ct-space-2); min-height: 58px; padding: 9px var(--ct-space-2); }
  .cultivation-overview__activity-type { grid-column: 1; }
  .cultivation-overview__activity-row p { grid-column: 1 / span 2; grid-row: 2; white-space: normal; }
  .cultivation-overview__activity-row time { grid-column: 2; grid-row: 1; }
}
</style>
