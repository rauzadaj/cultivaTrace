<template>
  <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" :maximized="isMobile" :full-width="isMobile">
    <q-card class="sensor-form">
      <q-card-section class="sensor-form__header">
        <div>
          <p class="sensor-form__eyebrow">Capteurs</p>
          <h2>Nouveau capteur</h2>
        </div>
        <q-btn flat round icon="mdi-close" aria-label="Fermer" @click="close" />
      </q-card-section>

      <q-form class="sensor-form__body" @submit.prevent="submit">
        <q-select v-model="form.type" :options="typeOptions" label="Type *" emit-value map-options outlined :rules="requiredRule" lazy-rules />
        <q-input v-model="form.deviceId" label="Identifiant appareil *" outlined :rules="requiredRule" lazy-rules />
        <q-select v-model="form.protocol" :options="protocolOptions" label="Protocole *" emit-value map-options outlined :rules="requiredRule" lazy-rules />
        <q-select
          v-model="form.room"
          :options="roomOptions"
          label="Salle *"
          emit-value
          map-options
          outlined
          :loading="roomsLoading"
          :rules="requiredRule"
          lazy-rules
        />

        <div class="sensor-form__thresholds">
          <q-input v-model.number="form.thresholdMin" label="Seuil minimum" type="number" outlined />
          <q-input v-model.number="form.thresholdMax" label="Seuil maximum" type="number" outlined />
          <q-input v-model="form.thresholdUnit" label="Unité" placeholder="°C, %, ppm..." outlined />
        </div>

        <div class="sensor-form__actions">
          <q-btn flat label="Annuler" class="action-btn" @click="close" />
          <q-btn color="primary" label="Ajouter" class="action-btn" :loading="submitting" type="submit" />
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

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
  (event: 'created', sensor: Sensor): void
}>()

const $q = useQuasar()
const isMobile = computed(() => $q.screen.width < 768)
const roomsLoading = ref(false)
const submitting = ref(false)
const requiredRule = [(v: unknown) => !!v || 'Champ obligatoire']

const form = reactive({
  type: 'temperature' as SensorType,
  deviceId: '',
  protocol: 'mqtt' as 'mqtt' | 'rest' | 'simulated',
  room: '',
  thresholdMin: null as number | null,
  thresholdMax: null as number | null,
  thresholdUnit: '',
})

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
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Chargement des salles impossible.' })
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
    const thresholds = form.thresholdMin !== null || form.thresholdMax !== null || form.thresholdUnit.trim()
      ? {
          min: Number(form.thresholdMin ?? 0),
          max: Number(form.thresholdMax ?? 0),
          unit: form.thresholdUnit.trim() || '',
        }
      : undefined

    const { data } = await sensorsApi.create({
      type: form.type,
      deviceId: form.deviceId.trim(),
      protocol: form.protocol,
      room: form.room,
      thresholds,
    })

    emit('created', data)
    close()
    resetForm()
    $q.notify({ type: 'positive', message: 'Capteur ajouté.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Création du capteur impossible.' })
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
