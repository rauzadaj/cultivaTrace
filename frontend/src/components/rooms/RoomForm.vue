<template>
  <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" :maximized="isMobile" :full-width="isMobile">
    <q-card class="room-form">
      <q-card-section class="room-form__header">
        <div>
          <p class="room-form__eyebrow">Rooms</p>
          <h2>New room</h2>
        </div>
        <q-btn flat round icon="mdi-close" aria-label="Close" @click="close" />
      </q-card-section>

      <q-form class="room-form__body" @submit.prevent="submit">
        <q-input
          v-model="form.name"
          label="Nom *"
          outlined
          :error="!!fieldErrors.name"
          :error-message="fieldErrors.name"
          @blur="validateField('name')"
          @update:model-value="clearFieldError('name')"
        />

        <q-select
          v-model="form.type"
          :options="typeOptions"
          label="Type *"
          emit-value
          map-options
          outlined
          :error="!!fieldErrors.type"
          :error-message="fieldErrors.type"
          @update:model-value="clearFieldError('type')"
        />

        <q-input
          v-model.number="form.capacityMax"
          label="Max capacity *"
          type="number"
          min="1"
          outlined
          :error="!!fieldErrors.capacityMax"
          :error-message="fieldErrors.capacityMax"
          @blur="validateField('capacityMax')"
          @update:model-value="clearFieldError('capacityMax')"
        />

        <q-input v-model="form.description" label="Description" outlined type="textarea" autogrow />


        <q-select
          v-model="form.farm"
          :options="farmOptions"
          label="Farm *"
          emit-value
          map-options
          outlined
          :loading="farmsLoading"
          :error="!!fieldErrors.farm"
          :error-message="fieldErrors.farm"
          @update:model-value="clearFieldError('farm')"
        />

        <q-banner v-if="formError" rounded class="room-form__error">
          {{ formError }}
        </q-banner>

        <div class="room-form__actions">
          <q-btn flat label="Cancel" class="action-btn" @click="close" />
          <q-btn color="primary" label="Create" class="action-btn" :loading="submitting" type="submit" />
        </div>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { farmsApi, roomsApi } from '@/services/api'
import type { Farm, RoomType } from '@/types/api'
import { useFormValidation, validators } from '@/composables/useFormValidation'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
  (event: 'created'): void
}>()

const $q = useQuasar()
const isMobile = computed(() => $q.screen.width < 768)
const submitting = ref(false)
const farmsLoading = ref(false)

const form = reactive({
  name: '',
  type: 'veg' as RoomType,
  capacityMax: 1,
  description: '',
  farm: '',
})
const { fieldErrors, formError, validateField, validateAll, clearFieldError, clearAllErrors, setFormError, applyApiError } = useFormValidation(
  form,
  {
    name: [validators.required('Name required.')],
    type: [validators.required('Type required.')],
    capacityMax: [validators.positiveInteger('Capacity required.')],
    farm: [validators.required('Farm required.')],
  },
)

const typeOptions: Array<{ label: string; value: RoomType }> = [
  { label: 'Vegetative', value: 'veg' },
  { label: 'Flower', value: 'flower' },
  { label: 'Drying', value: 'drying' },
  { label: 'Clone', value: 'clone' },
  { label: 'Mixed', value: 'mixed' },
]

const farmOptions = ref<Array<{ label: string; value: string }>>([])

watch(() => props.modelValue, (open) => {
  if (open) {
    void fetchFarms()
  }
})

async function fetchFarms() {
  farmsLoading.value = true
  try {
    const { data } = await farmsApi.list({ itemsPerPage: 200 })
    const farms = (data['hydra:member'] ?? data.member ?? []) as Farm[]
    farmOptions.value = farms.map((farm) => ({
      label: farm.name,
      value: farm['@id'] ?? `/api/farms/${farm.id}`,
    }))
    if (!form.farm && farmOptions.value.length) {
      form.farm = farmOptions.value[0].value
    }
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Failed to load farms.' })
  } finally {
    farmsLoading.value = false
  }
}

function close() {
  emit('update:modelValue', false)
}

function resetForm() {
  form.name = ''
  form.type = 'veg'
  form.capacityMax = 1
  form.description = ''
  form.farm = farmOptions.value[0]?.value ?? ''
}

async function submit() {
  submitting.value = true
  try {
    clearAllErrors()
    form.name = form.name.trim()

    if (!validateAll()) {
      setFormError('Fix invalid fields before creating the room.')
      return
    }

    await roomsApi.create({
      name: form.name,
      type: form.type,
      capacityMax: Number(form.capacityMax),
      description: form.description.trim() || undefined,
      farm: form.farm,
    })
    emit('created')
    close()
    resetForm()
    $q.notify({ type: 'positive', message: 'Room created.' })
  } catch (error) {
    applyApiError(error, 'Failed to create room.')
    $q.notify({ type: 'negative', message: formError.value || 'Failed to create room.' })
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped lang="scss">
.room-form {
  width: min(620px, 100vw);
}
.room-form__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.room-form__eyebrow {
  margin: 0;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #718096;
}
.room-form__body {
  display: grid;
  gap: 12px;
  padding: 0 16px 16px;
}
.room-form__error {
  color: #8c2f39;
  background: #fdecec;
  border: 1px solid #f3c9cf;
}
.room-form__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.action-btn {
  min-height: 48px;
}
</style>
