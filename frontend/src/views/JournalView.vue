<template>
  <main class="journal-view ct-theme">
    <header class="journal-header">
      <div class="journal-header__copy">
        <div class="journal-header__title-row">
          <div>
            <h1>Journal de Culture &amp; Activités Agronomiques</h1>
            <div class="journal-integrity"><span /> Registre append-only · {{ integrityLabel }}</div>
          </div>
          <q-btn v-if="canExport" outline no-caps icon="mdi-export-variant" label="Exports du registre" to="/reporting" class="journal-export" />
        </div>
        <p>Historique append-only des événements consignés et des données de traçabilité des plants.</p>
      </div>
    </header>

    <section class="journal-metrics" aria-label="Synthèse du journal pour la période sélectionnée">
      <journal-metric-card label="Actes enregistrés" :value="events.length" :detail="selectedDateLabel" icon="mdi-calendar-check-outline" icon-src="/design/executive/1-3152/7320e.svg" tone="asset" />
      <journal-metric-card label="Intrants consignés" :value="typeCounts.input_record ?? 0" detail="Événements avec intrant" icon="mdi-flask-outline" icon-src="/design/executive/1-3152/6be2a.svg" tone="asset" />
      <journal-metric-card label="Plants concernés" :value="uniquePlantCount" detail="Sur la date sélectionnée" icon="mdi-sprout-outline" tone="blue" />
      <journal-metric-card label="Empreintes SHA-256" :value="hashedEventCount" detail="Empreintes exposées" icon="mdi-fingerprint" icon-src="/design/executive/1-3152/a6bd1.svg" tone="asset" />
    </section>

    <section class="journal-toolbar" aria-label="Recherche et filtres du journal">
      <label class="journal-search">
        <q-icon name="mdi-magnify" size="17px" aria-hidden="true" />
        <input v-model="search" type="search" placeholder="Rechercher un opérateur, un plant ou un événement…" aria-label="Rechercher dans le journal">
        <q-btn v-if="search" flat round dense icon="mdi-close" aria-label="Effacer la recherche" @click="search = ''" />
      </label>
      <q-input :model-value="selectedDateLabel" class="journal-date" dense borderless readonly aria-label="Filtrer par date">
        <template #prepend><q-icon name="mdi-calendar-month-outline" size="16px" /></template>
        <q-popup-proxy v-model="datePickerOpen" cover transition-show="scale" transition-hide="scale">
          <q-date v-model="selectedDate" mask="YYYY-MM-DD" today-btn color="primary" />
        </q-popup-proxy>
      </q-input>
      <div class="journal-filters" role="group" aria-label="Filtrer par type d’événement">
        <button v-for="filter in filters" :key="filter.value" type="button" class="journal-filter" :class="{ 'journal-filter--active': activeType === filter.value }" :aria-pressed="activeType === filter.value" @click="activeType = filter.value">
          {{ filter.label }} <span>{{ filterCount(filter.value) }}</span>
        </button>
      </div>
      <q-btn flat round dense class="journal-refresh" icon="mdi-refresh" aria-label="Actualiser le journal" :loading="loading" @click="loadEvents" />
    </section>

    <div class="journal-layout">
      <section class="journal-feed" aria-label="Événements du journal">
        <div v-if="error" class="journal-state journal-state--error" role="alert">
          <q-icon name="mdi-alert-circle-outline" size="22px" />
          <span>{{ error }}</span>
          <q-btn flat no-caps label="Réessayer" @click="loadEvents" />
        </div>
        <template v-else>
          <div v-for="group in eventGroups" :key="group.key" class="journal-day">
            <header class="journal-day__heading">
              <div><span class="journal-day__dot" /> <h2>{{ group.label }}</h2></div>
              <span>{{ group.events.length }} {{ group.events.length === 1 ? 'événement' : 'événements' }}</span>
            </header>
            <journal-event-card v-for="event in group.events" :key="event.id" :event="event" :plant-name="plantName(event)" :plant-id="plantId(event)" :room-name="plantRoom(event)" :strain-name="plantStrain(event)" />
          </div>
          <div v-if="loading" class="journal-state"><q-spinner color="primary" size="24px" /> Chargement du registre…</div>
          <div v-else-if="!eventGroups.length" class="journal-state">
            <q-icon name="mdi-notebook-outline" size="28px" />
            <strong>Aucun événement pour ces filtres</strong>
            <span>Les événements s’afficheront ici lorsqu’ils auront été consignés.</span>
          </div>
        </template>
      </section>

      <aside class="journal-aside">
        <section class="journal-aside-card">
          <div class="journal-aside-card__heading"><img class="journal-integrity-asset" src="/design/executive/1-3152/01b11.svg" alt="" aria-hidden="true"><h2>Intégrité du registre</h2></div>
          <p>Les événements sont immuables et liés par empreinte SHA-256 côté serveur.</p>
          <div class="journal-integrity-row"><span>Empreintes exposées</span><strong>{{ hashedEventCount }} / {{ events.length }}</strong></div>
          <div class="journal-progress" role="progressbar" :aria-valuenow="hashCoverage" aria-valuemin="0" aria-valuemax="100"><span :style="{ width: `${hashCoverage}%` }" /></div>
        </section>

        <section class="journal-aside-card">
          <div class="journal-aside-card__heading"><q-icon name="mdi-chart-donut-variant" color="primary" size="18px" /><h2>Répartition des actes</h2></div>
          <p class="journal-aside-card__caption">{{ selectedDateLabel }} · données chargées</p>
          <div v-if="typeSummary.length" class="journal-breakdown">
            <div v-for="item in typeSummary" :key="item.type" class="journal-breakdown__item">
              <div><span>{{ eventTypeName(item.type) }}</span><strong>{{ item.count }}</strong></div>
              <div class="journal-progress"><span :style="{ width: `${item.percent}%` }" /></div>
            </div>
          </div>
          <p v-else class="journal-aside-card__empty">Aucun acte sur cette période.</p>
        </section>

        <section class="journal-aside-card journal-aside-card--note">
          <q-icon name="mdi-information-outline" size="17px" />
          <p>Cette vue présente les événements conservés dans le journal des plants. Les mesures de capteurs et les interventions non consignées ne sont pas ajoutées ici.</p>
        </section>
      </aside>
    </div>
  </main>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { usePlantsStore } from '@/stores/plants'
import { plantEventsApi } from '@/services/api'
import type { HydraCollection, PlantEvent } from '@/types/api'
import JournalEventCard from '@/components/journal/JournalEventCard.vue'
import JournalMetricCard from '@/components/journal/JournalMetricCard.vue'

const authStore = useAuthStore()
const plantsStore = usePlantsStore()
const loading = ref(false)
const error = ref('')
const datePickerOpen = ref(false)
const events = ref<PlantEvent[]>([])
const search = ref('')
const activeType = ref('all')
function localDateString(date = new Date()) {
  const local = new Date(date.getTime() - date.getTimezoneOffset() * 60_000)
  return local.toISOString().slice(0, 10)
}
const selectedDate = ref(localDateString())
const canExport = computed(() => authStore.isOrgAdmin || authStore.isSuperAdmin)

const filters = [
  { label: 'Tous', value: 'all' },
  { label: 'Transitions', value: 'stage_change' },
  { label: 'Intrants', value: 'input_record' },
  { label: 'Notes', value: 'note' },
  { label: 'Récoltes', value: 'harvest' },
  { label: 'Autres', value: 'other' },
] as const

const eventTypeLabels: Record<PlantEvent['eventType'], string> = {
  germination: 'Germination', stage_change: 'Transitions', note: 'Notes', photo: 'Photos', input_record: 'Intrants', harvest: 'Récoltes',
  destruction_intent: 'Destructions', destruction_confirmed: 'Destructions', room_move: 'Déplacements', legacy_activity: 'Activités',
}

function collectionMembers<T>(collection: HydraCollection<T>): T[] {
  return collection['hydra:member'] ?? collection.member ?? []
}

function dayBoundary(date: string, nextDay = false): string {
  const boundary = new Date(`${date}T00:00:00`)
  if (nextDay) boundary.setDate(boundary.getDate() + 1)
  return boundary.toISOString()
}

async function loadEvents() {
  if (!selectedDate.value) return
  loading.value = true
  error.value = ''
  try {
    const collected: PlantEvent[] = []
    let page = 1
    let total = 0
    do {
      const { data } = await plantEventsApi.listAll({
        'occurredAt[after]': dayBoundary(selectedDate.value),
        'occurredAt[before]': dayBoundary(selectedDate.value, true),
        'order[occurredAt]': 'desc',
        itemsPerPage: 100,
        page,
      })
      collected.push(...collectionMembers(data))
      total = data['hydra:totalItems'] ?? data.totalItems ?? collected.length
      page += 1
    } while (collected.length < total && page <= 100)
    events.value = collected
    await plantsStore.ensureSupportData()
  } catch (caught) {
    error.value = caught instanceof Error ? caught.message : 'Impossible de charger le registre.'
  } finally {
    loading.value = false
  }
}

const filteredEvents = computed(() => {
  const query = search.value.trim().toLocaleLowerCase('fr')
  return events.value.filter((event) => {
    const matchesType = activeType.value === 'all'
      || (activeType.value === 'other' ? !['stage_change', 'input_record', 'note', 'harvest'].includes(event.eventType) : event.eventType === activeType.value)
    if (!matchesType) return false
    if (!query) return true
    const searchable = [event.notes, eventTypeName(event.eventType), eventUser(event), plantName(event), JSON.stringify(event.payload ?? {})].join(' ').toLocaleLowerCase('fr')
    return searchable.includes(query)
  })
})

const eventGroups = computed(() => {
  const groups = new Map<string, PlantEvent[]>()
  for (const event of filteredEvents.value) {
    const key = new Date(event.occurredAt).toLocaleDateString('sv-SE')
    groups.set(key, [...(groups.get(key) ?? []), event])
  }
  return Array.from(groups, ([key, groupEvents]) => ({ key, label: dayLabel(key), events: groupEvents }))
})
const uniquePlantCount = computed(() => new Set(events.value.map((event) => plantId(event)).filter(Boolean)).size)
const typeCounts = computed(() => events.value.reduce<Record<string, number>>((counts, event) => {
  counts[event.eventType] = (counts[event.eventType] ?? 0) + 1
  return counts
}, {}))
const hashedEventCount = computed(() => events.value.filter((event) => Boolean(event.hashSelf)).length)
const hashCoverage = computed(() => events.value.length ? Math.round(hashedEventCount.value / events.value.length * 100) : 0)
const typeSummary = computed(() => Object.entries(typeCounts.value).map(([type, count]) => ({
  type,
  count,
  percent: events.value.length ? Math.max(4, Math.round(count / events.value.length * 100)) : 0,
})).sort((a, b) => b.count - a.count))
const selectedDateLabel = computed(() => new Date(`${selectedDate.value}T12:00:00`).toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' }))
const integrityLabel = computed(() => events.value.length ? `${hashedEventCount.value} empreinte${hashedEventCount.value === 1 ? '' : 's'} exposée${hashedEventCount.value === 1 ? '' : 's'}` : 'aucun événement sur cette date')

function eventTypeName(type: string) { return eventTypeLabels[type as PlantEvent['eventType']] ?? type }
function filterCount(type: string) {
  if (type === 'all') return events.value.length
  if (type === 'other') return events.value.filter((event) => !['stage_change', 'input_record', 'note', 'harvest'].includes(event.eventType)).length
  return typeCounts.value[type] ?? 0
}
function plantId(event: PlantEvent): string | null {
  const relation = event.plant
  if (!relation) return null
  return typeof relation === 'string' ? relation.split('/').pop() ?? null : relation.id
}
function plantName(event: PlantEvent): string {
  const id = plantId(event)
  if (!id) return 'Plant non renseigné'
  const plant = plantsStore.plants.find((item) => item.id === id)
  return plant ? plantsStore.plantName(plant) : `Plant ${id.slice(0, 8)}`
}
function plantRoom(event: PlantEvent): string {
  const id = plantId(event)
  const plant = plantsStore.plants.find((item) => item.id === id)
  return plant ? plantsStore.roomLabel(plant) : ''
}
function plantStrain(event: PlantEvent): string {
  const id = plantId(event)
  const plant = plantsStore.plants.find((item) => item.id === id)
  return plant ? plantsStore.strainLabel(plant) : ''
}
function eventUser(event: PlantEvent): string {
  return typeof event.user === 'string' ? '' : event.user?.email ?? ''
}
function dayLabel(key: string): string {
  const date = new Date(`${key}T12:00:00`)
  const today = new Date()
  const yesterday = new Date()
  yesterday.setDate(today.getDate() - 1)
  if (date.toDateString() === today.toDateString()) return `Aujourd’hui — ${date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}`
  if (date.toDateString() === yesterday.toDateString()) return `Hier — ${date.toLocaleDateString('fr-FR', { day: 'numeric', month: 'long', year: 'numeric' })}`
  return date.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

watch(selectedDate, () => { void loadEvents() })
onMounted(() => { void loadEvents() })
</script>

<style scoped>
.journal-view { display: grid; width: 100%; max-width: 1280px; gap: 0; margin-inline: auto; }
.journal-header { min-height: 113px; padding: 0 0 17px; border-bottom: 1px solid var(--ct-border); }
.journal-header__copy { max-width: 100%; }
.journal-header__title-row { display: flex; align-items: center; justify-content: space-between; gap: var(--ct-space-4); }
.journal-header h1 { margin: 0; color: var(--ct-text); font-size: clamp(22px, 2.2vw, 24px); font-weight: var(--ct-weight-semibold); line-height: 32px; letter-spacing: -.45px; }
.journal-header__copy > p { max-width: 556px; margin: var(--ct-space-1) 0 0; color: var(--ct-muted); font: 12px/16px var(--ct-font-serif); }
.journal-integrity { display: inline-flex; align-items: center; gap: var(--ct-space-2); margin-top: var(--ct-space-2); padding: 2px 9px; border: 1px solid color-mix(in srgb, var(--ct-mint) 28%, transparent); border-radius: var(--ct-radius-pill); color: var(--ct-mint); font: 11px/15px var(--ct-font-serif); }
.journal-integrity > span { width: 6px; height: 6px; border-radius: 50%; background: var(--ct-mint); }
.journal-export { flex: 0 0 auto; border-color: var(--ct-border-strong); color: var(--ct-text); font-size: 12px; }
.journal-metrics { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: var(--ct-space-3); padding-block: var(--ct-space-4); }
.journal-toolbar { display: flex; min-width: 0; min-height: 62px; align-items: center; gap: var(--ct-space-2); margin-bottom: var(--ct-space-4); padding: 12px 13px; border: 1px solid color-mix(in srgb, var(--ct-border-strong) 45%, transparent); border-radius: var(--ct-radius-md); background: var(--ct-surface); }
.journal-search { display: flex; height: 36px; min-width: 190px; flex: 1 1 280px; align-items: center; gap: var(--ct-space-2); padding: 0 10px; background: var(--ct-nav); color: var(--ct-muted); }
.journal-search input { width: 100%; min-width: 0; border: 0; outline: 0; background: transparent; color: var(--ct-text); font: 12px/16px var(--ct-font-sans); }
.journal-search input::placeholder { color: var(--ct-muted); opacity: 1; }
.journal-date { width: 178px; flex: 0 0 auto; padding-inline: var(--ct-space-2); background: var(--ct-nav); color: var(--ct-secondary); font-size: 12px; }
.journal-date :deep(.q-field__native), .journal-date :deep(.q-field__marginal) { color: var(--ct-secondary); }
.journal-filters { display: flex; min-width: 0; flex: 0 1 auto; align-items: center; gap: var(--ct-space-1); overflow-x: auto; scrollbar-width: thin; }
.journal-filter { display: inline-flex; min-height: 28px; flex: 0 0 auto; align-items: center; gap: var(--ct-space-1); padding: 0 9px; border: 1px solid transparent; border-radius: var(--ct-radius-sm); background: var(--ct-nav); color: var(--ct-secondary); font: 11px/14px var(--ct-font-sans); cursor: pointer; }
.journal-filter > span { color: var(--ct-muted); font: 11px/14px var(--ct-font-serif); }
.journal-filter:hover { border-color: var(--ct-border-strong); }
.journal-filter--active { border-color: color-mix(in srgb, var(--ct-mint) 35%, transparent); background: var(--ct-mint-soft); color: var(--ct-mint); }
.journal-filter--active > span { color: var(--ct-mint); }
.journal-refresh { width: 36px; min-width: 36px; height: 36px; min-height: 36px; color: var(--ct-secondary); }
.journal-layout { display: grid; grid-template-columns: minmax(0, 2.08fr) minmax(250px, 1fr); align-items: start; gap: var(--ct-space-6); }
.journal-feed, .journal-day { display: grid; min-width: 0; gap: var(--ct-space-3); }
.journal-day + .journal-day { margin-top: var(--ct-space-4); }
.journal-day__heading { display: flex; align-items: center; justify-content: space-between; gap: var(--ct-space-3); padding-bottom: var(--ct-space-2); border-bottom: 1px solid var(--ct-border); color: var(--ct-muted); }
.journal-day__heading > div { display: flex; align-items: center; gap: var(--ct-space-2); }
.journal-day__heading h2 { margin: 0; color: var(--ct-text); font-size: 12px; font-weight: var(--ct-weight-semibold); line-height: 16px; letter-spacing: .35px; text-transform: uppercase; }
.journal-day__heading > span { font: 11px/14px var(--ct-font-serif); }
.journal-day__dot { width: 8px; height: 8px; border-radius: 50%; background: var(--ct-mint); }
.journal-aside { display: grid; gap: var(--ct-space-4); }
.journal-aside-card { display: grid; gap: var(--ct-space-3); padding: var(--ct-space-4); border: 1px solid color-mix(in srgb, var(--ct-border-strong) 62%, transparent); border-radius: var(--ct-radius-md); background: var(--ct-surface); }
.journal-aside-card__heading { display: flex; align-items: center; gap: var(--ct-space-2); }
.journal-integrity-asset { display: block; flex: 0 0 auto; }
.journal-aside-card__heading h2 { margin: 0; color: var(--ct-text); font-size: 15px; font-weight: var(--ct-weight-semibold); line-height: 20px; }
.journal-aside-card > p, .journal-aside-card__caption { margin: 0; color: var(--ct-muted); font: 12px/17px var(--ct-font-serif); }
.journal-integrity-row, .journal-breakdown__item > div:first-child { display: flex; justify-content: space-between; gap: var(--ct-space-2); color: var(--ct-secondary); font: 12px/16px var(--ct-font-serif); }
.journal-integrity-row strong, .journal-breakdown__item strong { color: var(--ct-mint); font-weight: 400; }
.journal-progress { height: 6px; overflow: hidden; border-radius: var(--ct-radius-pill); background: var(--ct-nav); }
.journal-progress > span { display: block; height: 100%; border-radius: inherit; background: var(--ct-mint); transition: width .2s ease; }
.journal-breakdown { display: grid; gap: var(--ct-space-3); }
.journal-breakdown__item { display: grid; gap: var(--ct-space-2); }
.journal-breakdown__item:nth-child(3n + 2) .journal-progress > span { background: var(--ct-info); }
.journal-breakdown__item:nth-child(3n) .journal-progress > span { background: var(--ct-secondary); }
.journal-aside-card__empty { margin: 0; color: var(--ct-muted); font: 12px/17px var(--ct-font-serif); }
.journal-aside-card--note { grid-template-columns: auto minmax(0, 1fr); align-items: start; color: var(--ct-muted); }
.journal-aside-card--note p { margin: 0; color: var(--ct-muted); font: 11px/16px var(--ct-font-serif); }
.journal-state { display: flex; min-height: 160px; flex-direction: column; align-items: center; justify-content: center; gap: var(--ct-space-2); padding: var(--ct-space-6); color: var(--ct-muted); text-align: center; }
.journal-state strong { color: var(--ct-text); font-weight: var(--ct-weight-medium); }
.journal-state--error { flex-direction: row; flex-wrap: wrap; color: var(--ct-danger); }
@media (min-width: 1440px) { .journal-view { padding-inline: var(--ct-space-4); } }
@media (max-width: 1100px) { .journal-toolbar { flex-wrap: wrap; } .journal-search { flex-basis: 55%; } .journal-filters { order: 3; flex: 1 1 100%; } .journal-refresh { margin-left: auto; } }
@media (max-width: 900px) { .journal-layout { grid-template-columns: minmax(0, 1fr); } .journal-aside { grid-template-columns: repeat(2, minmax(0, 1fr)); } .journal-aside-card--note { grid-column: 1 / -1; } }
@media (max-width: 600px) { .journal-view { gap: var(--ct-space-3); } .journal-header__title-row { align-items: flex-start; flex-direction: column; } .journal-header h1 { font-size: 21px; line-height: 27px; } .journal-export { min-height: 40px; } .journal-metrics { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: var(--ct-space-2); } .journal-toolbar { padding: var(--ct-space-2); } .journal-search { flex: 1 1 100%; } .journal-date { width: calc(100% - 52px); flex: 1 1 auto; } .journal-refresh { width: 42px; min-width: 42px; height: 42px; min-height: 42px; } .journal-filters { flex-wrap: wrap; overflow: visible; } .journal-aside { grid-template-columns: minmax(0, 1fr); } .journal-aside-card--note { grid-column: auto; } }
</style>
