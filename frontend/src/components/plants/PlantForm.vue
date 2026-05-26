<template>
  <q-dialog :model-value="modelValue" @update:model-value="emit('update:modelValue', $event)" :maximized="isMobile" :full-width="isMobile">
    <q-card class="plant-form">
      <q-card-section class="plant-form__header">
        <div>
          <p class="plant-form__eyebrow">Plants</p>
          <h2>New plant</h2>
        </div>
        <q-btn flat round icon="mdi-close" aria-label="Close" @click="close" />
      </q-card-section>

      <q-form class="plant-form__body" @submit.prevent="submit">
        <q-select v-model="form.strain" :options="strainOptions" label="Genetics" emit-value map-options outlined clearable />
        <q-select
          v-model="form.room"
          :options="roomOptions"
          label="Room *"
          emit-value
          map-options
          outlined
          :error="!!fieldErrors.room"
          :error-message="fieldErrors.room"
          @update:model-value="clearFieldError('room')"
        />
        <q-input
          v-model="form.germinatedAt"
          type="date"
          label="Germination date *"
          outlined
          :error="!!fieldErrors.germinatedAt"
          :error-message="fieldErrors.germinatedAt"
          @blur="validateField('germinatedAt')"
          @update:model-value="clearFieldError('germinatedAt')"
        />
        <q-input v-model="form.rfidTag" label="RFID number" outlined />

        <!-- 402 plan-limit banner -->
        <q-banner v-if="planLimitError" rounded class="plant-form__plan-limit">
          <template #avatar>
            <q-icon name="mdi-alert-circle-outline" color="warning" />
          </template>
          <div class="plant-form__plan-limit-body">
            <strong>Plan limit reached</strong>
            <p>
              You have {{ planLimitError.current }} plants out of {{ planLimitError.max ?? '∞' }} allowed
              (plan {{ planLimitError.upgradeTo }}).
            </p>
            <q-btn
              flat
              dense
              color="primary"
              label="Upgrade"
              icon="mdi-arrow-up-circle-outline"
              :to="planLimitError.upgradeUrl"
              @click="close"
            />
          </div>
        </q-banner>

        <!-- 403 license/role banner -->
        <q-banner v-else-if="forbiddenError" rounded class="plant-form__forbidden">
          <template #avatar>
            <q-icon name="mdi-lock-outline" color="negative" />
          </template>
          {{ forbiddenError }}
        </q-banner>

        <!-- generic error banner -->
        <q-banner v-else-if="formError" rounded class="plant-form__error">
          {{ formError }}
        </q-banner>

        <div class="plant-form__actions">
          <q-btn flat label="Cancel" class="action-btn" @click="close" />
          <q-btn color="primary" label="Create" class="action-btn" :loading="submitting" type="submit" :disable="!!planLimitError || !!forbiddenError" />
        </div>
      </q-form>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch } from 'vue'
import { useQuasar } from 'quasar'
import { usePlantsStore } from '@/stores/plants'
import type { Plant, PlanLimitError } from '@/types/api'
import { useFormValidation, validators } from '@/composables/useFormValidation'

const props = defineProps<{ modelValue: boolean }>()
const emit = defineEmits<{
  (event: 'update:modelValue', value: boolean): void
  (event: 'created', plant: Plant): void
}>()

const $q = useQuasar()
const plantsStore = usePlantsStore()
const isMobile = computed(() => $q.screen.width < 768)
const submitting = ref(false)
const planLimitError = ref<PlanLimitError | null>(null)
const forbiddenError = ref<string | null>(null)

const form = reactive({
  room: '',
  strain: '',
  germinatedAt: new Date().toISOString().slice(0, 10),
  rfidTag: '',
})
const { fieldErrors, formError, validateField, validateAll, clearFieldError, clearAllErrors, setFormError, applyApiError } = useFormValidation(
  form,
  {
    room: [validators.required('Room required.')],
    germinatedAt: [validators.required('Germination date required.'), validators.isoDate('Invalid germination date.')],
  },
)

const roomOptions = computed(() => plantsStore.roomIriList())
const strainOptions = computed(() => plantsStore.strainIriList())

watch(() => props.modelValue, async (open) => {
  if (!open) return
  planLimitError.value = null
  forbiddenError.value = null
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

type AxiosLike = { response?: { status?: number; data?: Record<string, unknown> } }

async function submit() {
  submitting.value = true
  planLimitError.value = null
  forbiddenError.value = null
  try {
    clearAllErrors()

    if (!validateAll()) {
      setFormError('Fix required fields before creating the plant.')
      return
    }

    const plant = await plantsStore.createPlant({
      room: form.room,
      strain: form.strain || null,
      germinatedAt: new Date(form.germinatedAt).toISOString(),
      rfidTag: form.rfidTag || null,
    })
    emit('created', plant)
    close()
    form.rfidTag = ''
    $q.notify({ type: 'positive', message: 'Plant created.' })
  } catch (error) {
    const axiosError = error as AxiosLike
    const status = axiosError?.response?.status
    const data = axiosError?.response?.data

    if (status === 402 && data?.planLimit) {
      planLimitError.value = data.planLimit as PlanLimitError
    } else if (status === 403) {
      forbiddenError.value = (data?.detail as string | undefined) ?? 'Your license or role does not allow this action.'
    } else {
      applyApiError(error, 'Creation failed.')
      $q.notify({ type: 'negative', message: formError.value || 'Creation failed.' })
    }
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
.plant-form__error {
  color: #8c2f39;
  background: #fdecec;
  border: 1px solid #f3c9cf;
}
.plant-form__plan-limit {
  background: #fff8e1;
  border: 1px solid #ffe082;
  &-body { display: flex; flex-direction: column; gap: 4px; }
  p { margin: 0; font-size: 0.875rem; }
}
.plant-form__forbidden {
  color: #721c24;
  background: #f8d7da;
  border: 1px solid #f5c6cb;
}
.plant-form__actions { display: grid; gap: 10px; grid-template-columns: 1fr 1fr; }
.action-btn { min-height: 48px; }
</style>
