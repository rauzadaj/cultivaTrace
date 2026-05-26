<template>
  <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" :maximized="isMobile" :full-width="isMobile">
    <q-card class="thresholds-form">
      <q-card-section class="thresholds-form__header">
        <div>
          <p class="thresholds-form__eyebrow">Sensors</p>
          <h2>Edit thresholds</h2>
        </div>
        <q-btn flat round icon="mdi-close" aria-label="Close" @click="close" />
      </q-card-section>

      <q-form class="thresholds-form__body" @submit.prevent="submit">
        <q-input
          v-model.number="form.min"
          label="Minimum threshold *"
          type="number"
          outlined
          :error="!!fieldErrors.min"
          :error-message="fieldErrors.min"
          @update:model-value="clearFieldError('min')"
        />
        <q-input
          v-model.number="form.max"
          label="Maximum threshold *"
          type="number"
          outlined
          :error="!!fieldErrors.max"
          :error-message="fieldErrors.max"
          @update:model-value="clearFieldError('max')"
        />
        <q-input
          v-model="form.unit"
          label="Unit *"
          outlined
          :error="!!fieldErrors.unit"
          :error-message="fieldErrors.unit"
          @blur="validateField('unit')"
          @update:model-value="clearFieldError('unit')"
        />
        <q-input
          v-model.number="form.cooldownMinutes"
          label="Alert cooldown (min) *"
          type="number"
          min="1"
          outlined
          :error="!!fieldErrors.cooldownMinutes"
          :error-message="fieldErrors.cooldownMinutes"
          @blur="validateField('cooldownMinutes')"
          @update:model-value="clearFieldError('cooldownMinutes')"
        />

        <q-banner v-if="formError" rounded class="thresholds-form__error">
          {{ formError }}
        </q-banner>

        <div class="thresholds-form__actions">
          <q-btn flat label="Cancel" class="action-btn" @click="close" />
          <q-btn color="primary" label="Save" class="action-btn" :loading="submitting" type="submit" />
        </div>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { sensorsApi } from '@/services/api'
import type { Sensor } from '@/types/api'
import { useFormValidation, validators } from '@/composables/useFormValidation'

const props = defineProps<{
  modelValue: boolean
  sensor: Sensor | null
}>()

const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
  (event: 'saved', sensor: Sensor): void
}>()

const $q = useQuasar()
const isMobile = computed(() => $q.screen.width < 768)
const submitting = ref(false)

const form = reactive({
  min: 0,
  max: 0,
  unit: '',
  cooldownMinutes: 60,
})
const { fieldErrors, formError, validateField, validateAll, clearFieldError, clearAllErrors, setFormError, applyApiError } = useFormValidation(
  form,
  {
    min: [
      (value, values) => {
        if (typeof value !== 'number') {
          return 'Minimum threshold required.'
        }

        if (value > values.max) {
          return 'Minimum threshold must be less than maximum threshold.'
        }

        return null
      },
    ],
    max: [
      (value, values) => {
        if (typeof value !== 'number') {
          return 'Maximum threshold required.'
        }

        if (value < values.min) {
          return 'Maximum threshold must be greater than minimum threshold.'
        }

        return null
      },
    ],
    unit: [validators.required('Unit required.')],
    cooldownMinutes: [validators.positiveInteger('Cooldown required.')],
  },
)

watch(() => [props.modelValue, props.sensor] as const, ([open, sensor]) => {
  if (!open || !sensor) return

  form.min = sensor.thresholds?.min ?? 0
  form.max = sensor.thresholds?.max ?? 0
  form.unit = sensor.thresholds?.unit ?? ''
  form.cooldownMinutes = 60
}, { immediate: true })

function close() {
  emit('update:modelValue', false)
}

async function submit() {
  if (!props.sensor) {
    return
  }

  submitting.value = true

  try {
    clearAllErrors()
    form.unit = form.unit.trim()

    if (!validateAll()) {
      setFormError(‘Fix invalid fields before saving thresholds.’)
      return
    }

    const { data } = await sensorsApi.update(props.sensor.id, {
      thresholds: {
        min: Number(form.min),
        max: Number(form.max),
        unit: form.unit,
      },
    })
    emit('saved', data)
    close()
    $q.notify({ type: 'positive', message: 'Thresholds updated.' })
  } catch (error) {
    applyApiError(error, 'Update failed.')
    $q.notify({ type: 'negative', message: formError.value || 'Update failed.' })
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped lang="scss">
.thresholds-form {
  width: min(560px, 100vw);
}
.thresholds-form__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.thresholds-form__eyebrow {
  margin: 0;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #718096;
}
.thresholds-form__body {
  display: grid;
  gap: 12px;
  padding: 0 16px 16px;
}
.thresholds-form__error {
  color: #8c2f39;
  background: #fdecec;
  border: 1px solid #f3c9cf;
}
.thresholds-form__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.action-btn {
  min-height: 48px;
}
</style>
