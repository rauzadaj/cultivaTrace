<template>
  <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" :maximized="isMobile" :full-width="isMobile">
    <q-card class="sensor-form">
      <q-card-section class="sensor-form__header">
        <div>
          <p class="sensor-form__eyebrow">Sensors</p>
          <h2>New sensor</h2>
        </div>
        <q-btn flat round icon="mdi-close" aria-label="Close" @click="close" />
      </q-card-section>

      <q-form class="sensor-form__body" @submit.prevent="submit">
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
          v-model="form.deviceId"
          label="Device ID *"
          outlined
          :error="!!fieldErrors.deviceId"
          :error-message="fieldErrors.deviceId"
          @blur="validateField('deviceId')"
          @update:model-value="clearFieldError('deviceId')"
        />
        <q-select
          v-model="form.protocol"
          :options="protocolOptions"
          label="Protocole *"
          emit-value
          map-options
          outlined
          :error="!!fieldErrors.protocol"
          :error-message="fieldErrors.protocol"
          @update:model-value="clearFieldError('protocol')"
        />
        <q-select
          v-model="form.room"
          :options="roomOptions"
          label="Room *"
          emit-value
          map-options
          outlined
          :loading="roomsLoading"
          :error="!!fieldErrors.room"
          :error-message="fieldErrors.room"
          @update:model-value="clearFieldError('room')"
        />

        <div class="sensor-form__thresholds">
          <q-input
            v-model.number="form.thresholdMin"
            label="Minimum threshold"
            type="number"
            outlined
            :error="!!fieldErrors.thresholdMin"
            :error-message="fieldErrors.thresholdMin"
            @update:model-value="clearFieldError('thresholdMin')"
          />
          <q-input
            v-model.number="form.thresholdMax"
            label="Maximum threshold"
            type="number"
            outlined
            :error="!!fieldErrors.thresholdMax"
            :error-message="fieldErrors.thresholdMax"
            @update:model-value="clearFieldError('thresholdMax')"
          />
          <q-input
            v-model="form.thresholdUnit"
            label="Unit"
            placeholder="°C, %, ppm..."
            outlined
            :error="!!fieldErrors.thresholdUnit"
            :error-message="fieldErrors.thresholdUnit"
            @update:model-value="clearFieldError('thresholdUnit')"
          />
        </div>

        <q-banner v-if="formError" rounded class="sensor-form__error">
          {{ formError }}
        </q-banner>

        <div class="sensor-form__actions">
          <q-btn flat label="Cancel" class="action-btn" @click="close" />
          <q-btn color="primary" label="Add" class="action-btn" :loading="submitting" type="submit" />
        </div>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { roomsApi, sensorsApi } from '@/services/api'
import type { Room, Sensor, SensorType } from '@/types/api'
import { useFormValidation, validators } from '@/composables/useFormValidation'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
  (event: 'created', sensor: Sensor): void
}>()

const $q = useQuasar()
const isMobile = computed(() => $q.screen.width < 768)
const roomsLoading = ref(false)
const submitting = ref(false)

const form = reactive({
  type: 'temperature' as SensorType,
  deviceId: '',
  protocol: 'mqtt' as 'mqtt' | 'rest' | 'simulated',
  room: '',
  thresholdMin: null as number | null,
  thresholdMax: null as number | null,
  thresholdUnit: '',
})
const { fieldErrors, formError, validateField, validateAll, clearFieldError, clearAllErrors, setFormError, applyApiError } = useFormValidation(
  form,
  {
    type: [validators.required('Type required.')],
    deviceId: [validators.required('Device ID required.')],
    protocol: [validators.required('Protocol required.')],
    room: [validators.required('Room required.')],
    thresholdMin: [
      (value, values) => {
        if (value === null || value === undefined) {
          return null
        }

        if (values.thresholdMax !== null && value > values.thresholdMax) {
          return 'Minimum threshold must be less than maximum threshold.'
        }

        return null
      },
    ],
    thresholdMax: [
      (value, values) => {
        if (value === null || value === undefined) {
          return null
        }

        if (values.thresholdMin !== null && value < values.thresholdMin) {
          return 'Maximum threshold must be greater than minimum threshold.'
        }

        return null
      },
    ],
    thresholdUnit: [
      (value, values) => {
        const thresholdConfigured = values.thresholdMin !== null || values.thresholdMax !== null

        if (!thresholdConfigured) {
          return null
        }

        return typeof value === 'string' && value.trim() !== '' ? null : 'Unit required when a threshold is set.'
      },
    ],
  },
)

const typeOptions: Array<{ label: string; value: SensorType }> = [
  { label: 'Temperature', value: 'temperature' },
  { label: 'Humidity', value: 'humidity' },
  { label: 'CO2', value: 'co2' },
  { label: 'pH', value: 'ph' },
  { label: 'EC', value: 'ec' },
]

const protocolOptions = [
  { label: 'MQTT', value: 'mqtt' },
  { label: 'REST', value: 'rest' },
  { label: 'Simulated', value: 'simulated' },
]

const roomOptions = ref<Array<{ label: string; value: string }>>([])

watch(() => props.modelValue, (open) => {
  if (open) {
    void fetchRooms()
  }
})

async function fetchRooms() {
  roomsLoading.value = true
  try {
    const { data } = await roomsApi.list({ itemsPerPage: 200 })
    const rooms = (data['hydra:member'] ?? data.member ?? []) as Room[]
    roomOptions.value = rooms.map((room) => ({
      label: room.name,
      value: room['@id'] ?? `/api/rooms/${room.id}`,
    }))
    if (!form.room && roomOptions.value.length) {
      form.room = roomOptions.value[0].value
    }
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Failed to load rooms.' })
  } finally {
    roomsLoading.value = false
  }
}

function close() {
  emit('update:modelValue', false)
}

function resetForm() {
  form.type = 'temperature'
  form.deviceId = ''
  form.protocol = 'mqtt'
  form.room = roomOptions.value[0]?.value ?? ''
  form.thresholdMin = null
  form.thresholdMax = null
  form.thresholdUnit = ''
}

async function submit() {
  submitting.value = true

  try {
    clearAllErrors()
    form.deviceId = form.deviceId.trim()
    form.thresholdUnit = form.thresholdUnit.trim()

    if (!validateAll()) {
      setFormError('Fix invalid fields before creating the sensor.')
      return
    }

    const thresholds = form.thresholdMin !== null || form.thresholdMax !== null || form.thresholdUnit !== ''
      ? {
          min: Number(form.thresholdMin ?? 0),
          max: Number(form.thresholdMax ?? 0),
          unit: form.thresholdUnit,
        }
      : undefined

    const { data } = await sensorsApi.create({
      type: form.type,
      deviceId: form.deviceId,
      protocol: form.protocol,
      room: form.room,
      thresholds,
    })

    emit('created', data)
    close()
    resetForm()
    $q.notify({ type: 'positive', message: 'Sensor added.' })
  } catch (error) {
    applyApiError(error, 'Failed to create sensor.')
    $q.notify({ type: 'negative', message: formError.value || 'Failed to create sensor.' })
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped lang="scss">
.sensor-form {
  width: min(640px, 100vw);
}
.sensor-form__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.sensor-form__eyebrow {
  margin: 0;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #718096;
}
.sensor-form__body {
  display: grid;
  gap: 12px;
  padding: 0 16px 16px;
}
.sensor-form__error {
  color: #8c2f39;
  background: #fdecec;
  border: 1px solid #f3c9cf;
}
.sensor-form__thresholds {
  display: grid;
  gap: 10px;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
}
.sensor-form__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.action-btn {
  min-height: 48px;
}
</style>
