<template>
  <article class="journal-event">
    <div class="journal-event__heading">
      <div class="journal-event__identity">
        <span class="journal-event__time">{{ time }}</span>
        <q-icon :name="typeIcons[event.eventType] ?? 'mdi-note-outline'" size="17px" class="journal-event__type-icon" aria-hidden="true" />
        <h3>{{ typeLabel }}</h3>
        <q-badge v-if="event.hashSelf" class="journal-event__hash-badge">Empreinte présente</q-badge>
      </div>
      <span v-if="roomName" class="journal-event__type">Salle : {{ roomName }}</span>
    </div>
    <div class="journal-event__context">
      <RouterLink v-if="plantId" :to="{ name: 'plant-detail', params: { id: plantId } }" class="journal-event__plant">
        <q-icon name="mdi-sprout-outline" size="14px" />
        {{ plantName }}
      </RouterLink>
      <span v-if="roomName">· Salle {{ roomName }}</span>
      <span v-if="strainName">· {{ strainName }}</span>
      <span v-if="eventUser">·</span>
      <span v-if="eventUser">{{ eventUser }}</span>
    </div>
    <p v-if="event.notes" class="journal-event__notes">{{ event.notes }}</p>
    <div v-if="event.photoUrls?.length" class="journal-event__photos">
      <a v-for="(photo, index) in event.photoUrls" :key="`${event.id}-${index}`" :href="photo" target="_blank" rel="noreferrer">
        <img :src="photo" :alt="`Photo liée à l’événement ${index + 1}`" loading="lazy">
      </a>
    </div>
    <div v-if="payloadItems.length" class="journal-event__payload">
      <span v-for="([key, value]) in payloadItems" :key="key"><strong>{{ payloadLabel(key) }} :</strong> {{ displayValue(value) }}</span>
    </div>
    <footer class="journal-event__footer">
      <span v-if="eventUser">{{ eventUser }}</span>
      <span v-if="event.hashSelf" class="journal-event__hash" :title="event.hashSelf"><q-icon name="mdi-lock-outline" /> SHA-256 · {{ shortHash(event.hashSelf) }}</span>
      <span v-else class="journal-event__hash journal-event__hash--missing">Aucune empreinte exposée</span>
    </footer>
  </article>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import type { PlantEvent } from '@/types/api'

const props = defineProps<{
  event: PlantEvent
  plantName: string
  plantId: string | null
  roomName: string
  strainName: string
}>()

const typeNames: Record<PlantEvent['eventType'], string> = {
  germination: 'Germination', stage_change: 'Changement de stade', note: 'Note', photo: 'Photo',
  input_record: 'Intrant consigné', harvest: 'Récolte', destruction_intent: 'Destruction planifiée',
  destruction_confirmed: 'Destruction confirmée', room_move: 'Déplacement de salle', legacy_activity: 'Activité',
}
const typeIcons: Record<PlantEvent['eventType'], string> = {
  germination: 'mdi-sprout-outline', stage_change: 'mdi-swap-horizontal', note: 'mdi-note-text-outline', photo: 'mdi-camera-outline',
  input_record: 'mdi-flask-outline', harvest: 'mdi-basket-outline', destruction_intent: 'mdi-alert-outline',
  destruction_confirmed: 'mdi-delete-alert-outline', room_move: 'mdi-door-open', legacy_activity: 'mdi-history',
}
const typeLabel = computed(() => typeNames[props.event.eventType] ?? props.event.eventType)
const time = computed(() => new Date(props.event.occurredAt).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }))
const eventUser = computed(() => typeof props.event.user === 'string' ? '' : props.event.user?.email ?? '')
const payloadItems = computed(() => Object.entries(props.event.payload ?? {}).filter(([key, value]) => {
  if (value === null || value === undefined || /room_?id/i.test(key)) return false
  if (props.event.eventType === 'germination' && key === 'to') return false
  return true
}))
const payloadLabels: Record<string, string> = { from: 'Avant', to: 'Après', product: 'Produit', quantity: 'Quantité', unit: 'Unité', reason: 'Motif', planned_date: 'Date prévue', fromRoomId: 'Salle précédente', toRoomId: 'Nouvelle salle', gross_weight_g: 'Poids brut (g)', non_cannabis_ratio: 'Ratio non-cannabis' }
function payloadLabel(key: string) { return payloadLabels[key] ?? key.replaceAll('_', ' ') }
function displayValue(value: unknown) { return typeof value === 'object' ? JSON.stringify(value) : String(value) }
function shortHash(hash: string) { return `${hash.slice(0, 10)}…${hash.slice(-8)}` }
</script>

<style scoped>
.journal-event { display: grid; gap: var(--ct-space-3); padding: 17px; border: 1px solid color-mix(in srgb, var(--ct-border-strong) 55%, transparent); border-radius: var(--ct-radius-md); background: var(--ct-surface); }
.journal-event__heading, .journal-event__identity, .journal-event__context, .journal-event__footer { display: flex; align-items: center; gap: var(--ct-space-2); min-width: 0; }
.journal-event__heading, .journal-event__footer { justify-content: space-between; }
.journal-event__identity { flex-wrap: wrap; }
.journal-event__identity h3 { margin: 0; color: var(--ct-text); font-size: 16px; font-weight: var(--ct-weight-semibold); line-height: 24px; }
.journal-event__type-icon { color: var(--ct-mint); }
.journal-event__time { padding: 3px 8px; border: 1px solid color-mix(in srgb, var(--ct-mint) 20%, transparent); color: var(--ct-mint); font: 12px/16px var(--ct-font-serif); white-space: nowrap; }
.journal-event__hash-badge { color: var(--ct-mint); background: var(--ct-mint-soft); font-size: 10px; }
.journal-event__type, .journal-event__context, .journal-event__footer { color: var(--ct-muted); font: 12px/17px var(--ct-font-serif); }
.journal-event__type { white-space: nowrap; }
.journal-event__context { flex-wrap: wrap; }
.journal-event__plant { display: inline-flex; align-items: center; gap: var(--ct-space-1); color: var(--ct-secondary); text-decoration: none; }
.journal-event__plant:hover { color: var(--ct-mint); }
.journal-event__notes { margin: 0; padding: var(--ct-space-3); background: var(--ct-inset); color: var(--ct-secondary); line-height: var(--ct-line-body); overflow-wrap: anywhere; }
.journal-event__payload { display: flex; flex-wrap: wrap; gap: var(--ct-space-2) var(--ct-space-4); padding: var(--ct-space-3); background: var(--ct-inset); color: var(--ct-muted); font: 12px/17px var(--ct-font-serif); }
.journal-event__payload strong { color: var(--ct-secondary); font-weight: 400; text-transform: capitalize; }
.journal-event__photos { display: flex; gap: var(--ct-space-2); overflow-x: auto; }
.journal-event__photos a { display: block; width: 132px; height: 82px; flex: 0 0 auto; overflow: hidden; border-radius: var(--ct-radius-sm); }
.journal-event__photos img { width: 100%; height: 100%; object-fit: cover; }
.journal-event__footer { flex-wrap: wrap; padding-top: var(--ct-space-2); border-top: 1px solid color-mix(in srgb, var(--ct-border-strong) 30%, transparent); }
.journal-event__hash { display: inline-flex; align-items: center; gap: var(--ct-space-1); color: var(--ct-mint); }
.journal-event__hash--missing { color: var(--ct-muted); }
@media (max-width: 600px) { .journal-event { padding: var(--ct-space-3); } .journal-event__heading { align-items: flex-start; flex-direction: column; } }
</style>
