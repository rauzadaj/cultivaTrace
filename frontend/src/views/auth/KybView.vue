<template>
  <q-page class="kyb-page">
    <div class="kyb-container">

      <!-- Header -->
      <div class="kyb-header q-mb-xl">
        <div class="text-h5 text-weight-bold text-primary">Vérification de licence</div>
        <div class="text-body2 text-grey-6 q-mt-xs">
          CannaSaaS est réservé aux opérateurs licenciés.
          Soumettez votre licence pour activer votre accès complet.
        </div>
      </div>

      <!-- Status actuel -->
      <q-banner
        v-if="kybStatus"
        :class="statusBannerClass"
        rounded
        class="q-mb-lg"
      >
        <template #avatar>
          <q-icon :name="statusIcon" />
        </template>
        <div class="text-weight-medium">{{ statusMessage }}</div>
        <div v-if="kybStatus.licenseExpiresAt" class="text-caption q-mt-xs">
          Expire le {{ formatDate(kybStatus.licenseExpiresAt) }}
        </div>
      </q-banner>

      <q-banner v-if="statusError" rounded class="bg-negative text-white q-mb-lg">
        <div class="text-weight-medium">{{ statusError }}</div>
        <q-btn flat color="white" no-caps label="Réessayer" class="q-mt-sm" @click="retryStatus" />
      </q-banner>

      <!-- Formulaire de soumission -->
      <q-card v-if="showForm" flat bordered class="kyb-form-card">
        <q-card-section>
          <div class="text-subtitle1 text-weight-medium q-mb-md">
            Soumettre votre licence
          </div>

          <q-select
            v-model="form.licenseType"
            :options="licenseTypeOptions"
            label="Type de licence *"
            outlined
            emit-value
            map-options
            class="q-mb-md"
            :error="!!fieldErrors.licenseType"
            :error-message="fieldErrors.licenseType"
            @update:model-value="clearFieldError('licenseType')"
          />

          <q-input
            v-model="form.licenseNumber"
            label="Numéro de licence *"
            outlined
            class="q-mb-md"
            :hint="licenseNumberHint"
            placeholder="Ex: HC-LP-12345 / CO-LIC-67890"
            :error="!!fieldErrors.licenseNumber"
            :error-message="fieldErrors.licenseNumber"
            @blur="validateField('licenseNumber')"
            @update:model-value="clearFieldError('licenseNumber')"
          />

          <div class="q-mb-md">
            <div class="text-caption text-grey-6 q-mb-sm">
              Document de licence (PDF ou image) — optionnel en développement
            </div>
            <q-file
              v-model="form.file"
              label="Choisir un fichier"
              outlined
              accept=".pdf,.jpg,.jpeg,.png"
              max-file-size="5242880"
              :error="!!fieldErrors.file"
              :error-message="fieldErrors.file"
              @update:model-value="clearFieldError('file')"
            >
              <template #prepend>
                <q-icon name="attach_file" />
              </template>
            </q-file>
          </div>

          <q-banner v-if="submitError" rounded class="bg-negative text-white q-mt-md">
            <div class="text-weight-medium">{{ submitError }}</div>
          </q-banner>

          <q-btn
            label="Soumettre la licence"
            color="primary"
            unelevated
            :loading="loading"
            :disable="!form.licenseNumber || !form.licenseType"
            class="full-width q-mt-sm"
            style="height: 48px"
            @click="submitLicense"
          />

          <q-btn
            v-if="submitError"
            flat
            color="primary"
            no-caps
            label="Réessayer l’envoi"
            class="full-width q-mt-sm"
            @click="submitLicense"
          />
        </q-card-section>
      </q-card>

      <!-- Résultat de la vérification -->
      <q-card v-if="result" flat bordered class="q-mt-lg">
        <q-card-section>
          <div class="row items-center q-gutter-sm q-mb-md">
            <q-icon
              :name="result.status === 'active' ? 'check_circle' : result.status === 'pending' ? 'schedule' : 'cancel'"
              :color="result.status === 'active' ? 'positive' : result.status === 'pending' ? 'warning' : 'negative'"
              size="md"
            />
            <div class="text-subtitle1 text-weight-medium">{{ result.message }}</div>
          </div>

          <div class="text-body2 text-grey-7">
            Méthode de vérification : {{ result.verificationMethod }}
          </div>
        </q-card-section>

        <q-card-actions v-if="result.status === 'active'">
          <q-btn
            label="Accéder au dashboard"
            color="primary"
            flat
            @click="goToDashboard"
          />
        </q-card-actions>
      </q-card>

    </div>
  </q-page>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { useRouter } from 'vue-router'
import { kybApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import { useFormValidation, validators } from '@/composables/useFormValidation'
import type { KybStatusResponse, KybSubmitResponse } from '@/types/api'

const $q = useQuasar()
const router = useRouter()
const authStore = useAuthStore()

const loading    = ref(false)
const kybStatus  = ref<KybStatusResponse | null>(null)
const result     = ref<KybSubmitResponse | null>(null)
const statusError = ref('')
const submitError = ref('')

const form = ref({
  licenseType:   '',
  licenseNumber: '',
  file:          null as File | null,
})
const { fieldErrors, validateField, validateAll, clearFieldError, clearAllErrors, applyApiError } = useFormValidation(
  {
    get licenseType() {
      return form.value.licenseType
    },
    get licenseNumber() {
      return form.value.licenseNumber
    },
    get file() {
      return form.value.file
    },
  },
  {
    licenseType: [validators.required('Type de licence requis.')],
    licenseNumber: [validators.required('Numéro de licence requis.')],
    file: [
      (value) => {
        if (!(value instanceof File)) {
          return null
        }

        return value.size <= 5 * 1024 * 1024 ? null : 'Fichier trop volumineux (5 Mo max).'
      },
    ],
  },
)

const devSimulationEnabled = import.meta.env.VITE_KYB_ENABLE_DEV_SIMULATION === '1'

const licenseTypeOptions = [
  { label: 'Health Canada (Canada)', value: 'health_canada' },
  { label: 'METRC (USA)', value: 'metrc_usa' },
  { label: 'BfArM (Allemagne)', value: 'bfarm_de' },
  { label: 'ANSM (France)', value: 'ansm_fr' },
  ...(devSimulationEnabled ? [{ label: 'CTLS fictive (dev)', value: 'ctls_dev' }] : []),
]

const licenseNumberHint = computed(() => {
  const hints: Record<string, string> = {
    health_canada: 'Format : LP-XXXXXXXX (ex: LP-12345678)',
    ctls_dev:      'Format dev : TEST-CTLS-XXXX (ex: TEST-CTLS-DEMO-001)',
    metrc_usa:     'Format : {ÉTAT}-LIC-XXXXX (ex: CO-LIC-12345)',
    bfarm_de:      'Format : BfArM-DE-XXXXX',
    ansm_fr:       'Format : ANSM-FR-XXXXX',
  }
  return hints[form.value.licenseType] ?? 'Entrez votre numéro de licence officiel'
})

const showForm = computed(() =>
  !kybStatus.value ||
  kybStatus.value.licenseStatus === 'pending' ||
  kybStatus.value.licenseStatus === 'rejected' ||
  kybStatus.value.licenseStatus === 'expired'
)

const statusBannerClass = computed(() => {
  const map: Record<string, string> = {
    active:    'bg-positive text-white',
    pending:   'bg-warning text-dark',
    rejected:  'bg-negative text-white',
    expired:   'bg-negative text-white',
    suspended: 'bg-negative text-white',
  }
  return map[kybStatus.value?.licenseStatus] ?? 'bg-grey-3'
})

const statusIcon = computed(() => {
  const map: Record<string, string> = {
    active:    'check_circle',
    pending:   'schedule',
    rejected:  'cancel',
    expired:   'warning',
    suspended: 'lock',
  }
  return map[kybStatus.value?.licenseStatus] ?? 'info'
})

const statusMessage = computed(() => {
  const map: Record<string, string> = {
    active:    'Licence vérifiée — Accès complet activé',
    pending:   'Vérification en cours — Accès limité',
    rejected:  'Licence rejetée — Veuillez soumettre une nouvelle licence valide',
    expired:   'Licence expirée — Renouvelez votre licence',
    suspended: 'Compte suspendu — contactez l’équipe conformité.',
  }
  return map[kybStatus.value?.licenseStatus] ?? 'Statut inconnu'
})

async function loadStatus(): Promise<void> {
  statusError.value = ''
  try {
    const { data } = await kybApi.status()
    kybStatus.value = data
  } catch (error) {
    const axiosError = error as { response?: { data?: { error?: string } } }
    statusError.value = axiosError.response?.data?.error ?? 'Impossible de charger le statut KYB.'
  }
}

async function submitLicense(): Promise<void> {
  loading.value = true
  submitError.value = ''
  clearAllErrors()
  try {
    form.value.licenseNumber = form.value.licenseNumber.trim()

    if (!validateAll()) {
      submitError.value = 'Corrigez les champs invalides avant de soumettre.'
      return
    }

    const formData = new FormData()
    formData.append('licenseNumber', form.value.licenseNumber)
    formData.append('licenseType', form.value.licenseType)
    if (form.value.file) {
      formData.append('file', form.value.file)
    }

    const { data } = await kybApi.upload(formData)
    result.value = data

    await loadStatus()

    if (data.status === 'active') {
      await authStore.fetchMe()
      $q.notify({ type: 'positive', message: '✅ Licence validée ! Accès complet activé.' })
    } else if (data.status === 'pending') {
      $q.notify({ type: 'warning', message: '⏳ Vérification manuelle en cours (24-48h).' })
    } else if (data.status === 'rejected') {
      $q.notify({ type: 'negative', message: 'Licence rejetée. Corrigez les informations avant une nouvelle tentative.' })
    }
  } catch (e) {
    applyApiError(e, 'Erreur lors de la soumission.')
    submitError.value = submitError.value || 'Erreur lors de la soumission.'
    $q.notify({ type: 'negative', message: submitError.value })
  } finally {
    loading.value = false
  }
}

async function retryStatus(): Promise<void> {
  await loadStatus()
}

function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('fr-FR')
}

function goToDashboard(): void {
  void router.push('/dashboard/overview')
}

onMounted(loadStatus)
</script>

<style scoped>
.kyb-page { background: var(--q-color-grey-1, #f7f8fa); }
.kyb-container { max-width: 560px; margin: 0 auto; padding: 32px 16px; }
.kyb-form-card { border-radius: 12px; }
</style>
