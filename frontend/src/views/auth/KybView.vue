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
          />

          <q-input
            v-model="form.licenseNumber"
            label="Numéro de licence *"
            outlined
            class="q-mb-md"
            :hint="licenseNumberHint"
            placeholder="Ex: HC-LP-12345 / CO-LIC-67890"
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
            >
              <template #prepend>
                <q-icon name="attach_file" />
              </template>
            </q-file>
          </div>

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

const $q = useQuasar()
const router = useRouter()
const authStore = useAuthStore()

const loading    = ref(false)
const kybStatus  = ref<any>(null)
const result     = ref<any>(null)

const form = ref({
  licenseType:   '',
  licenseNumber: '',
  file:          null as File | null,
})

const licenseTypeOptions = [
  { label: 'Health Canada (Canada)', value: 'health_canada' },
  { label: 'CTLS fictive (dev)', value: 'ctls_dev' },
  { label: 'METRC (USA)', value: 'metrc_usa' },
  { label: 'BfArM (Allemagne)', value: 'bfarm_de' },
  { label: 'ANSM (France)', value: 'ansm_fr' },
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
    suspended: 'Compte suspendu — Contactez support@cannas.app',
  }
  return map[kybStatus.value?.licenseStatus] ?? 'Statut inconnu'
})

async function loadStatus(): Promise<void> {
  try {
    const { data } = await kybApi.status()
    kybStatus.value = data
  } catch {
    // Pas de statut KYB encore
  }
}

async function submitLicense(): Promise<void> {
  if (!form.value.licenseNumber || !form.value.licenseType) return

  loading.value = true
  try {
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
    }
  } catch (e: any) {
    $q.notify({ type: 'negative', message: e.response?.data?.error ?? 'Erreur lors de la soumission' })
  } finally {
    loading.value = false
  }
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
