<template>
  <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" :maximized="isMobile" :full-width="isMobile">
    <q-card class="thresholds-form">
      <q-card-section class="thresholds-form__header">
        <div>
          <p class="thresholds-form__eyebrow">Capteurs</p>
          <h2>Modifier seuils</h2>
        </div>
        <q-btn flat round icon="mdi-close" aria-label="Fermer" @click="close" />
      </q-card-section>

      <q-form class="thresholds-form__body" @submit.prevent="submit">
        <q-input v-model.number="form.min" label="Seuil minimum *" type="number" outlined :rules="requiredRule" lazy-rules />
        <q-input v-model.number="form.max" label="Seuil maximum *" type="number" outlined :rules="requiredRule" lazy-rules />
        <q-input v-model="form.unit" label="Unité *" outlined :rules="requiredRule" lazy-rules />
        <q-input v-model.number="form.cooldownMinutes" label="Cooldown alertes (min) *" type="number" min="1" outlined :rules="requiredRule" lazy-rules />

        <div class="thresholds-form__actions">
          <q-btn flat label="Annuler" class="action-btn" @click="close" />
          <q-btn color="primary" label="Enregistrer" class="action-btn" :loading="submitting" type="submit" />
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
const requiredRule = [(v: unknown) => !!v || 'Champ obligatoire']

const form = reactive({
  min: 0,
  max: 0,
  unit: '',
  cooldownMinutes: 60,
})

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
    const { data } = await sensorsApi.update(props.sensor.id, {
      thresholds: {
        min: Number(form.min),
        max: Number(form.max),
        unit: form.unit.trim(),
      },
    })
    emit('saved', data)
    close()
    $q.notify({ type: 'positive', message: 'Seuils mis à jour.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Mise à jour impossible.' })
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
.thresholds-form__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.action-btn {
  min-height: 48px;
}
</style>
