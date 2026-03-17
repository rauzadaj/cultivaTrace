<template>
  <div>
    <div class="section-grid section-grid--metrics mb-1">
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Services</span>
          <strong>{{ filteredServices.length }}</strong>
          <small>Published operational modules</small>
      </q-card>
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Primary tone</span>
          <strong>{{ countByTone('primary') }}</strong>
          <small>Monitoring and catalog services</small>
      </q-card>
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Warning tone</span>
          <strong>{{ countByTone('warning') }}</strong>
          <small>Chemistry and alerts</small>
      </q-card>
      <q-card flat class="surface-card metric-card">
          <span class="metric-card__label">Success tone</span>
          <strong>{{ countByTone('success') }}</strong>
          <small>Compliant or healthy services</small>
      </q-card>
    </div>

    <div class="section-grid section-grid--content">
      <div>
        <q-card flat class="surface-card panel-card">
          <div class="section-header">
            <div>
              <p class="section-header__eyebrow">Services</p>
              <h2>Operational services</h2>
            </div>
            <q-chip size="sm" outline color="primary">{{ filteredServices.length }} services</q-chip>
          </div>

          <div v-if="!filteredServices.length" class="empty-state">No service available for this filter.</div>
          <div v-else class="table-shell">
            <header class="table-head">
              <span>Name</span>
              <span>Category</span>
              <span>Status</span>
              <span>Position</span>
              <span>Actions</span>
            </header>

            <article v-for="service in filteredServices" :key="service.id" class="table-row">
              <div class="table-row__primary">
                <span class="service-icon" :class="`service-icon--${service.tone}`">
                  <q-icon :name="service.icon" size="18px" />
                </span>
                <div>
                  <strong>{{ service.name }}</strong>
                  <p>{{ service.description }}</p>
                </div>
              </div>
              <div>
                <strong>{{ service.category }}</strong>
                <p>{{ formatDate(service.updatedAt) }}</p>
              </div>
              <div>
                <strong>{{ service.statusLabel }}</strong>
                <p>{{ service.tone }}</p>
              </div>
              <div>
                <strong>#{{ service.position }}</strong>
              </div>
              <div class="action-stack">
                <q-btn size="sm" outline color="primary" @click="startEdit(service)">Edit</q-btn>
                <q-btn size="sm" flat color="negative" @click="removeService(service)">Delete</q-btn>
              </div>
            </article>
          </div>
        </q-card>
      </div>

      <div>
        <q-card flat class="surface-card panel-card">
          <div class="section-header section-header--compact">
            <div>
              <p class="section-header__eyebrow">Service editor</p>
              <h2>{{ mode === 'create' ? 'Create service' : 'Update service' }}</h2>
            </div>
            <q-btn v-if="mode === 'edit'" flat color="primary" @click="resetForm">New</q-btn>
          </div>

          <form class="form-grid" @submit.prevent="submitService">
            <q-input v-model="form.name" label="Name" outlined :disabled="saving" />
            <q-input v-model="form.category" label="Category" outlined :disabled="saving" />
            <q-input v-model="form.icon" label="Material icon" hint="Example: mdi-flask-outline" outlined :disabled="saving" />
            <q-select
              v-model="form.tone"
              label="Tone"
              :items="toneOptions"
              option-label="label"
              option-value="value"
              outlined
              emit-value
              map-options
              :disabled="saving"
            />
            <q-input v-model="form.statusLabel" label="Status label" outlined :disabled="saving" />
            <q-input v-model="form.position" label="Position" type="number" min="0" outlined :disabled="saving" />
            <q-input v-model="form.description" label="Description" type="textarea" autogrow outlined :disabled="saving" />
            <q-btn type="submit" color="primary" unelevated rounded class="full-width" :loading="saving">
              {{ mode === 'create' ? 'Create service' : 'Save changes' }}
            </q-btn>
          </form>
        </q-card>

        <ServicesCard title="Published services" dense :items="serviceCards" class="mt-4" />
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import ServicesCard from './ServicesCard.vue'
import { useCropStore } from '../../stores/useCropStore'
import type { OperationalServiceDto } from '../../types/api'

const props = defineProps<{
  search: string
}>()

const $q = useQuasar()
const cropStore = useCropStore()
const saving = ref(false)
const mode = ref<'create' | 'edit'>('create')
const form = reactive({
  iri: '',
  name: '',
  category: '',
  description: '',
  icon: 'mdi-flask-outline',
  tone: 'primary' as OperationalServiceDto['tone'],
  statusLabel: '',
  position: '10',
})

const toneOptions: Array<{ label: string; value: OperationalServiceDto['tone'] }> = [
  { label: 'Primary', value: 'primary' },
  { label: 'Warning', value: 'warning' },
  { label: 'Success', value: 'success' },
]

const filteredServices = computed(() => {
  const needle = props.search.trim().toLowerCase()

  if (!needle) {
    return cropStore.services
  }

  return cropStore.services.filter((service) => [
    service.name,
    service.category,
    service.description,
    service.statusLabel,
  ].join(' ').toLowerCase().includes(needle))
})

const serviceCards = computed(() => cropStore.services.map((service) => ({
  title: service.name,
  description: service.description,
  icon: service.icon,
  tone: service.tone,
  status: service.statusLabel,
})))

function countByTone(tone: OperationalServiceDto['tone']) {
  return cropStore.services.filter((service) => service.tone === tone).length
}

function formatDate(value: string) {
  return new Date(value).toLocaleString('fr-FR', {
    day: '2-digit',
    month: 'short',
    hour: '2-digit',
    minute: '2-digit',
  })
}

function resetForm() {
  mode.value = 'create'
  form.iri = ''
  form.name = ''
  form.category = ''
  form.description = ''
  form.icon = 'mdi-flask-outline'
  form.tone = 'primary'
  form.statusLabel = ''
  form.position = '10'
}

function startEdit(service: OperationalServiceDto) {
  mode.value = 'edit'
  form.iri = service['@id']
  form.name = service.name
  form.category = service.category
  form.description = service.description
  form.icon = service.icon
  form.tone = service.tone
  form.statusLabel = service.statusLabel
  form.position = String(service.position)
}

async function submitService() {
  saving.value = true

  try {
    const payload = {
      name: form.name.trim(),
      category: form.category.trim(),
      description: form.description.trim(),
      icon: form.icon.trim(),
      tone: form.tone,
      statusLabel: form.statusLabel.trim(),
      position: Number.parseInt(form.position, 10),
    }

    if (!payload.name || !payload.category || !payload.description || !payload.icon || !payload.statusLabel) {
      throw new Error('All service fields are required.')
    }

    if (!Number.isInteger(payload.position) || payload.position < 0) {
      throw new Error('Position must be a non-negative integer.')
    }

    if (mode.value === 'create') {
      await cropStore.createService(payload)
      $q.notify({ type: 'positive', message: 'Service created.', position: 'top-right', timeout: 3000 })
    } else {
      await cropStore.updateService(form.iri, payload)
      $q.notify({ type: 'positive', message: 'Service updated.', position: 'top-right', timeout: 3000 })
    }

    resetForm()
  } catch (error) {
    $q.notify({
      type: 'negative',
      message: error instanceof Error ? error.message : 'Unable to save the service.',
      position: 'top-right',
      timeout: 4000,
    })
  } finally {
    saving.value = false
  }
}

async function removeService(service: OperationalServiceDto) {
  if (!window.confirm(`Delete service ${service.name}?`)) {
    return
  }

  await cropStore.deleteService(service['@id'])
  $q.notify({ type: 'positive', message: 'Service deleted.', position: 'top-right', timeout: 3000 })

  if (form.iri === service['@id']) {
    resetForm()
  }
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

.section-grid {
  display: grid;
  gap: 12px;
}

.section-grid--metrics {
  grid-template-columns: repeat(4, minmax(0, 1fr));
}

.section-grid--content {
  grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
  align-items: start;
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

.section-header--compact {
  margin-bottom: 14px;
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

.table-shell {
  display: grid;
  gap: 10px;
}

.table-head,
.table-row {
  display: grid;
  grid-template-columns: minmax(220px, 1.5fr) 130px 130px 80px 130px;
  gap: 12px;
  align-items: center;
}

.table-head {
  padding: 0 14px 8px;
  color: #90a4ae;
  font-size: 0.76rem;
  font-weight: 600;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.table-row {
  padding: 14px;
  border: 1px solid #e4e8ec;
  border-radius: 10px;
  background: #fafbfc;
}

.table-row__primary {
  display: flex;
  align-items: center;
  gap: 12px;
}

.table-row strong {
  display: block;
  font-size: 0.96rem;
  font-weight: 600;
}

.table-row p {
  margin: 0;
  color: #78909c;
}

.service-icon {
  display: grid;
  place-items: center;
  width: 34px;
  height: 34px;
  border-radius: 10px;
}

.service-icon--primary {
  background: #e0f7fa;
  color: #00838f;
}

.service-icon--warning {
  background: #fff3e0;
  color: #ef6c00;
}

.service-icon--success {
  background: #e8f5e9;
  color: #2e7d32;
}

.form-grid {
  display: grid;
  gap: 14px;
}

.action-stack {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.empty-state {
  padding: 18px;
  border: 1px dashed #ccd6dd;
  border-radius: 8px;
  background: #fafcfd;
  color: #78909c;
  text-align: center;
}

@media (max-width: 1279px) {
  .table-head {
    display: none;
  }

  .table-row {
    grid-template-columns: 1fr;
  }

  .section-grid--content {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 959px) {
  .section-grid--metrics {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 640px) {
  .section-grid--metrics {
    grid-template-columns: 1fr;
  }

  .section-header {
    flex-direction: column;
    align-items: flex-start;
  }
}
</style>
