<template>
  <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" :maximized="isMobile" :full-width="isMobile">
    <q-card class="farm-form">
      <q-card-section class="farm-form__header">
        <div>
          <p class="farm-form__eyebrow">Farms</p>
          <h2>New farm</h2>
        </div>
        <q-btn flat round icon="mdi-close" aria-label="Close" @click="close" />
      </q-card-section>

      <q-form class="farm-form__body" @submit.prevent="submit">
        <q-input
          v-model="form.name"
          label="Nom *"
          outlined
          lazy-rules
          :rules="[(v: string) => !!v?.trim() || 'Name is required']"
        />
        <q-input v-model="form.address" label="Address" outlined />
        <q-input
          v-model.number="form.surfaceM2"
          label="Surface (m²)"
          type="number"
          min="0"
          outlined
        />

        <div class="farm-form__actions">
          <q-btn flat label="Cancel" class="action-btn" @click="close" />
          <q-btn color="primary" label="Create" class="action-btn" :loading="submitting" type="submit" />
        </div>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref } from 'vue'
import { useQuasar } from 'quasar'
import { farmsApi } from '@/services/api'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
  (event: 'created'): void
}>()

const $q = useQuasar()
const isMobile = computed(() => $q.screen.width < 768)
const submitting = ref(false)

const form = reactive({
  name: '',
  address: '',
  surfaceM2: null as number | null,
})

function close() {
  emit('update:modelValue', false)
}

function resetForm() {
  form.name = ''
  form.address = ''
  form.surfaceM2 = null
}

async function submit() {
  submitting.value = true

  try {
    await farmsApi.create({
      name: form.name.trim(),
      address: form.address.trim() || undefined,
      surfaceM2: form.surfaceM2 ?? undefined,
    })
    emit('created')
    close()
    resetForm()
    $q.notify({ type: 'positive', message: 'Farm created.' })
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Creation failed.' })
  } finally {
    submitting.value = false
  }
}
</script>

<style scoped lang="scss">
.farm-form {
  width: min(560px, 100vw);
}
.farm-form__header {
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.farm-form__eyebrow {
  margin: 0;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.1em;
  color: #718096;
}
.farm-form__body {
  display: grid;
  gap: 12px;
  padding: 0 16px 16px;
}
.farm-form__actions {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}
.action-btn {
  min-height: 48px;
}
</style>
