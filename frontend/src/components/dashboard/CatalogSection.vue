<template>
  <div>
    <v-row dense class="mb-1">
      <v-col cols="12" md="6" xl="3">
        <v-card flat class="surface-card metric-card">
          <span class="metric-card__label">Catalog lines</span>
          <strong>{{ filteredSeeds.length }}</strong>
          <small>Imported seed cards with media</small>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" xl="3">
        <v-card flat class="surface-card metric-card">
          <span class="metric-card__label">California-ready</span>
          <strong>{{ californiaCount }}</strong>
          <small>Tagged for California workflows</small>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" xl="3">
        <v-card flat class="surface-card metric-card">
          <span class="metric-card__label">Canada-ready</span>
          <strong>{{ canadaCount }}</strong>
          <small>Tagged for Canadian workflows</small>
        </v-card>
      </v-col>
      <v-col cols="12" md="6" xl="3">
        <v-card flat class="surface-card metric-card">
          <span class="metric-card__label">Primary source</span>
          <strong>{{ primaryVendor }}</strong>
          <small>Single-source verified catalog</small>
        </v-card>
      </v-col>
    </v-row>

    <v-card flat class="surface-card panel-card">
      <div class="section-header">
        <div>
          <p class="section-header__eyebrow">Seed catalog</p>
          <h2>California + Canada seed library</h2>
        </div>
        <v-chip size="small" variant="tonal" color="primary">{{ filteredSeeds.length }} entries</v-chip>
      </div>

      <div v-if="!filteredSeeds.length" class="empty-state">
        No seed card matches the current filter.
      </div>

      <div v-else class="catalog-grid">
        <article v-for="seed in filteredSeeds" :key="seed.id" class="catalog-card">
          <div class="catalog-card__media">
            <v-img
              :src="seed.imageUrl"
              :alt="seed.name"
              cover
              height="220"
              class="catalog-card__image"
            >
              <template #placeholder>
                <div class="catalog-card__placeholder">Loading image</div>
              </template>
            </v-img>
            <div class="catalog-card__overlay">
              <v-chip size="x-small" color="white" variant="flat">{{ seed.code }}</v-chip>
              <v-chip size="x-small" color="primary" variant="flat">{{ seed.typeLabel }}</v-chip>
            </div>
          </div>

          <div class="catalog-card__body">
            <div class="catalog-card__header">
              <div>
                <h3>{{ seed.name }}</h3>
                <p>{{ seed.vendor }}</p>
              </div>
              <v-btn
                icon="mdi-open-in-new"
                size="small"
                variant="text"
                color="primary"
                :href="seed.sourceUrl"
                target="_blank"
                rel="noreferrer"
              />
            </div>

            <div class="catalog-card__chips">
              <v-chip
                v-for="market in seed.markets"
                :key="`${seed.id}-${market}`"
                size="small"
                variant="outlined"
                color="secondary"
              >
                {{ market }}
              </v-chip>
            </div>

            <dl class="catalog-card__facts">
              <div>
                <dt>Genetics</dt>
                <dd>{{ seed.genetics }}</dd>
              </div>
              <div v-if="seed.thcRange">
                <dt>THC</dt>
                <dd>{{ seed.thcRange }}</dd>
              </div>
              <div v-if="seed.flavorProfile">
                <dt>Flavor</dt>
                <dd>{{ seed.flavorProfile }}</dd>
              </div>
              <div v-if="seed.lastUpdated">
                <dt>Updated</dt>
                <dd>{{ seed.lastUpdated }}</dd>
              </div>
            </dl>

            <p class="catalog-card__description">{{ seed.description }}</p>
          </div>
        </article>
      </div>
    </v-card>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useCropStore } from '../../stores/useCropStore'
import type { GeneticDto } from '../../types/api'

const props = defineProps<{
  search: string
}>()

interface CatalogSeedCard {
  id: string
  code: string
  name: string
  vendor: string
  genetics: string
  description: string
  imageUrl: string
  sourceUrl: string
  typeLabel: string
  thcRange: string | null
  flavorProfile: string | null
  markets: string[]
  lastUpdated: string | null
}

const cropStore = useCropStore()

const catalogSeeds = computed<CatalogSeedCard[]>(() => cropStore.genetics
  .map(mapCatalogSeed)
  .filter((seed): seed is CatalogSeedCard => seed !== null)
  .sort((left, right) => left.name.localeCompare(right.name)))

const filteredSeeds = computed(() => {
  const needle = props.search.trim().toLowerCase()

  if (!needle) {
    return catalogSeeds.value
  }

  return catalogSeeds.value.filter((seed) => [
    seed.code,
    seed.name,
    seed.vendor,
    seed.genetics,
    seed.description,
    seed.typeLabel,
    seed.markets.join(' '),
    seed.flavorProfile ?? '',
  ].join(' ').toLowerCase().includes(needle))
})

const californiaCount = computed(() => filteredSeeds.value.filter((seed) => seed.markets.includes('california')).length)
const canadaCount = computed(() => filteredSeeds.value.filter((seed) => seed.markets.includes('canada')).length)
const primaryVendor = computed(() => filteredSeeds.value[0]?.vendor ?? '—')

function mapCatalogSeed(genetic: GeneticDto): CatalogSeedCard | null {
  const metadata = genetic.metadata as Record<string, unknown>
  const markets = Array.isArray(metadata.markets) ? metadata.markets.filter(isStringValue) : []
  const nestedMetadata = isRecord(metadata.metadata) ? metadata.metadata : {}
  const imageUrl = getString(metadata.imageUrl)
  const sourceUrl = getString(metadata.sourceUrl)
  const description = getString(metadata.description)
  const genetics = getString(metadata.genetics)

  if (!imageUrl || !sourceUrl || !description || !genetics || markets.length === 0) {
    return null
  }

  return {
    id: genetic.id,
    code: getString(metadata.code) || genetic.code,
    name: getString(metadata.name) || genetic.name,
    vendor: getString(metadata.vendor) || genetic.vendor || 'Unknown vendor',
    genetics,
    description,
    imageUrl,
    sourceUrl,
    typeLabel: getString(nestedMetadata.type) || 'Seed line',
    thcRange: getString(nestedMetadata.thcRange),
    flavorProfile: getString(nestedMetadata.flavorProfile),
    markets,
    lastUpdated: formatDate(getString(metadata.sourceModifiedAt)),
  }
}

function isRecord(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function isStringValue(value: unknown): value is string {
  return typeof value === 'string' && value.trim().length > 0
}

function getString(value: unknown): string | null {
  return typeof value === 'string' && value.trim() ? value.trim() : null
}

function formatDate(value: string | null): string | null {
  if (!value) {
    return null
  }

  return new Date(value).toLocaleDateString('fr-FR', {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  })
}
</script>

<style scoped>
.surface-card {
  border: 1px solid #dbe4ea;
  border-radius: 12px;
  background: #fff;
  box-shadow: 0 1px 2px rgba(23, 35, 45, 0.06);
}

.metric-card,
.panel-card {
  padding: 18px;
}

.metric-card {
  display: grid;
  gap: 4px;
}

.metric-card__label {
  color: #78909c;
  font-size: 0.78rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
}

.metric-card strong {
  color: #37474f;
  font-size: 1.75rem;
  font-weight: 600;
}

.metric-card small {
  color: #607d8b;
}

.section-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
  margin-bottom: 18px;
}

.section-header__eyebrow {
  margin: 0 0 4px;
  color: #90a4ae;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.section-header h2 {
  margin: 0;
  color: #37474f;
  font-size: 1.3rem;
  font-weight: 500;
}

.catalog-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 18px;
}

.catalog-card {
  overflow: hidden;
  border: 1px solid #e4e8ec;
  border-radius: 14px;
  background: #fafbfc;
}

.catalog-card__media {
  position: relative;
}

.catalog-card__image {
  background: linear-gradient(135deg, #eef4f7, #d9e6eb);
}

.catalog-card__overlay {
  position: absolute;
  top: 12px;
  left: 12px;
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.catalog-card__placeholder {
  display: grid;
  place-items: center;
  height: 100%;
  color: #607d8b;
  background: linear-gradient(135deg, #eef4f7, #dde7ec);
}

.catalog-card__body {
  display: grid;
  gap: 14px;
  padding: 16px;
}

.catalog-card__header {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.catalog-card__header h3 {
  margin: 0 0 4px;
  color: #263238;
  font-size: 1.08rem;
}

.catalog-card__header p {
  margin: 0;
  color: #78909c;
}

.catalog-card__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
}

.catalog-card__facts {
  display: grid;
  gap: 12px;
  margin: 0;
}

.catalog-card__facts div {
  display: grid;
  gap: 4px;
}

.catalog-card__facts dt {
  color: #90a4ae;
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.catalog-card__facts dd {
  margin: 0;
  color: #37474f;
  font-weight: 500;
}

.catalog-card__description {
  margin: 0;
  color: #546e7a;
  line-height: 1.55;
}

.empty-state {
  padding: 18px;
  border: 1px dashed #ccd6dd;
  border-radius: 8px;
  background: #fafcfd;
  color: #78909c;
  text-align: center;
}

@media (max-width: 640px) {
  .section-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .catalog-grid {
    grid-template-columns: 1fr;
  }
}
</style>
