<template>
  <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" :maximized="isMobile" :full-width="isMobile">
    <q-card class="plant-form">
      <q-card-section class="plant-form__header">
        <div>
          <p class="plant-form__eyebrow">Plants</p>
          <h2>Nouveau plant</h2>
        </div>
        <q-btn flat round icon="mdi-close" aria-label="Fermer" @click="close" />
      </q-card-section>

      <q-form class="plant-form__body" @submit.prevent="submit">
        <q-select v-model="form.strain" :options="strainOptions" label="Genetique" emit-value map-options outlined clearable />
        <q-select v-model="form.room" :options="roomOptions" label="Salle *" emit-value map-options outlined :rules="requiredRule" lazy-rules />
        <q-input v-model="form.germinatedAt" type="date" label="Date de germination *" outlined :rules="requiredRule" lazy-rules />
        <q-input v-model="form.rfidTag" label="Numero RFID" outlined />

        <div class="plant-form__actions">
          <q-btn flat label="Annuler" class="action-btn" @click="close" />
          <q-btn color="primary" label="Creer" class="action-btn" :loading="submitting" type="submit" />
        </div>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { usePlantsStore } from '@/stores/plants'
import type { Plant } from '@/types/api'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
  (event: 'created', plant: Plant): void
}>()

const $q = useQuasar()
const plantsStore = usePlantsStore()
const isMobile = computed(() => $q.screen.width < 768)
const submitting = ref(false)
const requiredRule = [(value: unknown) => !!value || 'Champ obligatoire']

const form = reactive({
  room: '',
  strain: '',
  germinatedAt: new Date().toISOString().slice(0, 10),
  rfidTag: '',
})

const roomOptions = computed(() => plantsStore.roomIriList())
const strainOptions = computed(() => plantsStore.strainIriList())

watch(() => props.modelValue, async (open) => {
  if (!open) return
  if (!plantsStore.rooms.length || !plantsStore.strains.length) {
    await plantsStore.fetchSupportData()
  }
  if (!form.room && roomOptions.value.length) {
    form.room = roomOptions.value[0].value
  }
}, { immediate: true })

function close() {
  emit('update:modelValue', false)
}

async function submit() {
  submitting.value = true
  try {
    const plant = await plantsStore.createPlant({
      room: form.room,
      strain: form.strain || null,
      germinatedAt: new Date(form.germinatedAt).toISOString(),
      rfidTag: form.rfidTag || null,
    })
    emit('created', plant)
    close()
    form.rfidTag = ''
    $q.notify({ type: 'positive', message: 'Plant cree.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Creation impossible.' })
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped lang="scss">
.plant-form { width: min(620px, 100vw); }
.plant-form__header { display: flex; align-items: center; justify-content: space-between; }
.plant-form__eyebrow {
  margin: 0;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #718096;
}
.plant-form__body { display: grid; gap: 12px; padding: 0 16px 16px; }
.plant-form__actions { display: grid; gap: 10px; grid-template-columns: 1fr 1fr; }
.action-btn { min-height: 48px; }
</style>
