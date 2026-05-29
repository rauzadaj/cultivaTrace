<template>
  <div class="catalog-mappings-view">
    <header class="catalog-mappings-view__header">
      <div>
        <p class="eyebrow">Catalog</p>
        <h1>Genetic catalog mappings</h1>
        <p>Review proposed links between supplier catalog entries and internal genetics.</p>
      </div>
      <q-btn flat color="primary" no-caps label="Refresh" :loading="loading" @click="loadMappings" />
    </header>

    <q-tabs v-model="activeStatus" class="catalog-mappings-view__tabs" no-caps inline-label align="left">
      <q-tab name="pending" :label="`Pending (${counts.pending})`" />
      <q-tab name="linked" :label="`Linked (${counts.linked})`" />
      <q-tab name="rejected" :label="`Rejected (${counts.rejected})`" />
    </q-tabs>

    <q-card class="catalog-mappings-card">
      <q-card-section v-if="loading" class="catalog-mappings-card__state">
        <q-spinner-dots size="32px" color="primary" />
      </q-card-section>

      <q-card-section v-else-if="!visibleMappings.length" class="catalog-mappings-card__state">
        <p>No {{ activeStatus }} mappings.</p>
      </q-card-section>

      <q-card-section v-else>
        <article
          v-for="mapping in visibleMappings"
          :key="mapping.id"
          class="catalog-mapping"
          data-test="mapping-row"
        >
          <div class="catalog-mapping__pair">
            <div class="catalog-mapping__side">
              <small>External</small>
              <strong>{{ mapping.externalCatalogEntry?.name ?? '—' }}</strong>
              <code>{{ mapping.externalCatalogEntry?.externalCode ?? '—' }}</code>
            </div>
            <q-icon name="mdi-arrow-right-thin" size="24px" class="catalog-mapping__arrow" />
            <div class="catalog-mapping__side">
              <small>Internal genetic</small>
              <strong>{{ mapping.genetic?.name ?? '—' }}</strong>
              <code>{{ mapping.genetic?.code ?? '—' }}</code>
            </div>
          </div>

          <div class="catalog-mapping__meta">
            <q-badge :color="statusColor(mapping.status)" :label="mapping.status" />
            <div v-if="mapping.status === 'pending'" class="catalog-mapping__actions">
              <q-btn
                color="positive"
                no-caps
                dense
                label="Approve"
                data-test="approve-btn"
                :loading="busyId === mapping.id"
                @click="review(mapping, 'approve')"
              />
              <q-btn
                color="negative"
                no-caps
                dense
                outline
                label="Reject"
                data-test="reject-btn"
                :loading="busyId === mapping.id"
                @click="review(mapping, 'reject')"
              />
            </div>
          </div>
        </article>
      </q-card-section>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useQuasar } from 'quasar'
import { catalogMappingApi } from '@/services/api'
import type { CatalogMappingStatus, GeneticCatalogMapping, HydraCollection } from '@/types/api'

const $q = useQuasar()
const loading = ref(false)
const busyId = ref<string | null>(null)
const mappings = ref<GeneticCatalogMapping[]>([])
const activeStatus = ref<CatalogMappingStatus>('pending')

function collectionMembers<T>(collection: HydraCollection<T>): T[] {
  return collection['hydra:member'] ?? collection.member ?? []
}

const counts = computed(() => ({
  pending: mappings.value.filter((m) => m.status === 'pending').length,
  linked: mappings.value.filter((m) => m.status === 'linked').length,
  rejected: mappings.value.filter((m) => m.status === 'rejected').length,
}))

const visibleMappings = computed(() =>
  mappings.value.filter((m) => m.status === activeStatus.value),
)

function statusColor(status: CatalogMappingStatus): string {
  if (status === 'linked') return 'positive'
  if (status === 'rejected') return 'negative'
  return 'warning'
}

async function loadMappings() {
  loading.value = true
  try {
    const { data } = await catalogMappingApi.list()
    mappings.value = collectionMembers(data)
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Unable to load mappings.' })
  } finally {
    loading.value = false
  }
}

async function review(mapping: GeneticCatalogMapping, action: 'approve' | 'reject') {
  busyId.value = mapping.id
  try {
    const { data } = action === 'approve'
      ? await catalogMappingApi.approve(mapping.id)
      : await catalogMappingApi.reject(mapping.id)

    const index = mappings.value.findIndex((m) => m.id === mapping.id)
    if (index !== -1) {
      mappings.value.splice(index, 1, data)
    }
    $q.notify({ type: 'positive', message: `Mapping ${action === 'approve' ? 'linked' : 'rejected'}.` })
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Review failed.' })
  } finally {
    busyId.value = null
  }
}

onMounted(loadMappings)
</script>

<style scoped>
.catalog-mappings-view {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.catalog-mappings-view__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.eyebrow {
  text-transform: uppercase;
  letter-spacing: 0.08em;
  font-size: 0.72rem;
  color: var(--q-primary);
  margin: 0;
}

.catalog-mapping {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 12px 0;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.catalog-mapping:last-child {
  border-bottom: none;
}

.catalog-mapping__pair {
  display: flex;
  align-items: center;
  gap: 16px;
}

.catalog-mapping__side {
  display: flex;
  flex-direction: column;
}

.catalog-mapping__side small {
  text-transform: uppercase;
  letter-spacing: 0.06em;
  font-size: 0.68rem;
  opacity: 0.6;
}

.catalog-mapping__arrow {
  opacity: 0.5;
}

.catalog-mapping__meta {
  display: flex;
  align-items: center;
  gap: 12px;
}

.catalog-mapping__actions {
  display: flex;
  gap: 8px;
}

.catalog-mappings-card__state {
  display: flex;
  justify-content: center;
  padding: 32px 0;
  opacity: 0.7;
}
</style>
