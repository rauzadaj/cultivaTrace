<template>
  <q-page class="billing-page">
    <div class="billing-container">

      <div class="text-h5 text-weight-bold q-mb-xs">Billing</div>
      <div class="text-body2 text-grey-6 q-mb-xl">Manage your CultivaTrace subscription</div>

      <q-banner v-if="billingLoadError" rounded class="bg-negative text-white q-mb-lg">
        <template #avatar><q-icon name="warning" /></template>
        <div class="text-weight-medium">{{ billingLoadError }}</div>
        <q-btn flat color="white" no-caps label="Retry" @click="loadBillingStatus" />
      </q-banner>

      <!-- Plan actuel -->
      <q-card flat bordered class="q-mb-lg current-plan-card">
        <q-card-section>
          <div class="row items-center justify-between">
            <div>
              <div class="text-caption text-grey-6 text-uppercase">Current plan</div>
              <div class="text-h6 text-weight-bold text-primary q-mt-xs">
                {{ planLabel }}
              </div>
              <div class="text-body2 text-grey-7 q-mt-xs">
                This is the plan currently applied to your organization.
              </div>
            </div>
            <q-chip
              :color="licenseStatusColor"
              text-color="white"
              :label="licenseStatusLabel"
              size="md"
            />
          </div>

          <!-- Limites -->
          <div v-if="limits" class="q-mt-md">
            <div class="row q-col-gutter-md">
              <div class="col-4" v-for="limit in displayLimits" :key="limit.key">
                <div class="text-caption text-grey-6">{{ limit.label }}</div>
                <div class="text-body1 text-weight-medium">
                  {{ limit.current }}
                  <span class="text-grey-5 text-caption">/ {{ limit.max ?? '∞' }}</span>
                </div>
                <q-linear-progress
                  v-if="limit.max"
                  :value="limit.current / limit.max"
                  :color="limit.current / limit.max > 0.8 ? 'negative' : 'primary'"
                  size="4px"
                  class="q-mt-xs"
                />
              </div>
            </div>
          </div>
        </q-card-section>

        <q-card-actions v-if="hasStripeSubscription">
          <q-btn
            label="Manage my subscription"
            color="primary"
            outline
            :loading="loadingPortal"
            @click="openPortal"
          />
        </q-card-actions>
      </q-card>

      <!-- Plans disponibles -->
      <div class="text-subtitle1 text-weight-medium q-mb-md">Change plan</div>

      <div class="row q-col-gutter-md">
        <div class="col-12 col-md-4" v-for="plan in plans" :key="plan.id">
          <q-card
            flat
            bordered
            :class="[
              'plan-card',
              {
                'plan-card--current': plan.id === currentPlan,
                'plan-card--recommended': plan.id === recommendedPlan && plan.id !== currentPlan,
              },
            ]"
          >
            <div class="plan-badges">
              <q-badge
                v-if="plan.id === currentPlan"
                color="positive"
                label="Active plan"
                class="plan-badge plan-badge--current"
              />
              <q-badge
                v-if="plan.id === recommendedPlan && plan.id !== currentPlan"
                color="primary"
                label="Recommended"
                class="plan-badge"
              />
            </div>

            <q-card-section>
              <div class="row items-center justify-between no-wrap q-gutter-sm">
                <div class="text-subtitle1 text-weight-bold">{{ plan.name }}</div>
                <q-chip
                  v-if="plan.id === currentPlan"
                  color="positive"
                  text-color="white"
                  dense
                  label="Active"
                />
              </div>
              <div class="text-h4 text-weight-bold text-primary q-my-sm">
                {{ plan.price }}
                <span class="text-caption text-grey-6 text-weight-regular">/mo</span>
              </div>

              <q-separator class="q-my-md" />

              <div v-for="feature in plan.features" :key="feature" class="row items-start q-mb-sm">
                <q-icon name="check" color="positive" size="sm" class="q-mr-sm q-mt-xs" />
                <span class="text-body2">{{ feature }}</span>
              </div>
            </q-card-section>

            <q-card-actions class="q-px-md q-pb-md">
              <q-btn
                v-if="plan.id !== currentPlan"
                :label="plan.id === 'enterprise' ? 'Contact us' : actionLabel(plan.id)"
                color="primary"
                :outline="plan.id !== recommendedPlan"
                :unelevated="plan.id === recommendedPlan"
                :loading="loadingCheckout === plan.id"
                class="full-width"
                style="height: 48px"
                @click="plan.id === 'enterprise' ? contactSales() : startCheckout(plan.id)"
              />
              <q-btn
                v-else
                label="Current plan"
                color="grey-4"
                text-color="grey-7"
                unelevated
                disable
                class="full-width"
                style="height: 48px"
              />
            </q-card-actions>
          </q-card>
        </div>
      </div>

    </div>
  </q-page>

  <!-- Enterprise contact dialog -->
  <q-dialog v-model="showContactDialog" persistent>
    <q-card style="min-width: 480px; max-width: 560px; width: 100%">
      <q-card-section class="row items-center q-pb-none">
        <div class="text-h6">Contact our sales team</div>
        <q-space />
        <q-btn icon="close" flat round dense v-close-popup :disable="contactLoading" />
      </q-card-section>

      <q-card-section class="q-pt-md">
        <div class="text-body2 text-grey-7 q-mb-lg">
          Tell us about your project and we'll get back to you within one business day.
        </div>
        <div class="q-gutter-md">
          <q-input
            v-model="contactForm.name"
            label="Full name *"
            outlined dense
            :error="!!contactErrors.name"
            :error-message="contactErrors.name"
            @update:model-value="contactErrors.name = ''"
          />
          <q-input
            v-model="contactForm.email"
            label="Email *"
            type="email"
            outlined dense
            :error="!!contactErrors.email"
            :error-message="contactErrors.email"
            @update:model-value="contactErrors.email = ''"
          />
          <q-input
            v-model="contactForm.company"
            label="Company"
            outlined dense
          />
          <q-input
            v-model="contactForm.message"
            label="Message *"
            type="textarea"
            outlined dense
            rows="4"
            :error="!!contactErrors.message"
            :error-message="contactErrors.message"
            @update:model-value="contactErrors.message = ''"
          />
        </div>
        <q-banner v-if="contactError" rounded class="bg-negative text-white q-mt-md">
          {{ contactError }}
        </q-banner>
      </q-card-section>

      <q-card-actions align="right" class="q-px-md q-pb-md">
        <q-btn flat label="Cancel" v-close-popup :disable="contactLoading" />
        <q-btn
          label="Send message"
          color="primary"
          unelevated
          :loading="contactLoading"
          @click="submitContact"
        />
      </q-card-actions>
    </q-card>
  </q-dialog>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import { billingApi } from '@/services/api'

const $q       = useQuasar()
const auth     = useAuthStore()

const limits         = ref<any>(null)
const loadingPortal  = ref(false)
const loadingCheckout = ref<string | null>(null)
const hasStripeSubscription = ref(false)
const resolvedPlan = ref<string | null>(null)
const billingLoadError = ref('')

// Contact dialog
const showContactDialog = ref(false)
const contactLoading = ref(false)
const contactError = ref('')
const contactForm = ref({ name: '', email: '', company: '', message: '' })
const contactErrors = ref({ name: '', email: '', message: '' })

const currentPlan = computed(() => resolvedPlan.value ?? auth.organization?.plan ?? 'growth')
const recommendedPlan = computed(() => 'pro')

const planLabel = computed(() => {
  const labels: Record<string, string> = {
    growth: 'Growth — €249/mo',
    pro: 'Pro — €499/mo',
    scale: 'Scale — €999/mo',
    enterprise: 'Enterprise — Custom pricing',
  }
  return labels[currentPlan.value] ?? currentPlan.value
})

const licenseStatusColor = computed(() => {
  const map: Record<string, string> = {
    active: 'positive', pending: 'warning',
    rejected: 'negative', expired: 'negative', suspended: 'negative',
  }
  return map[auth.organization?.licenseStatus ?? ''] ?? 'grey'
})

const licenseStatusLabel = computed(() => {
  const map: Record<string, string> = {
    active: 'Active license', pending: 'Pending',
    rejected: 'Rejected', expired: 'Expired', suspended: 'Suspended',
  }
  return map[auth.organization?.licenseStatus ?? ''] ?? 'Unknown'
})

const displayLimits = computed(() => {
  if (!limits.value) return []
  return [
    { key: 'plants', label: 'Plants', current: limits.value.plants.current, max: limits.value.plants.max },
    { key: 'farms',  label: 'Sites',  current: limits.value.farms.current,  max: limits.value.farms.max  },
    { key: 'users',  label: 'Users',  current: limits.value.users.current,  max: limits.value.users.max  },
  ]
})

const plans = [
  {
    id: 'growth', name: 'Growth', price: '249 €',
    features: ['500 plants', '1 site', 'IoT sensors', 'PDF reports', 'Audit trail'],
  },
  {
    id: 'pro', name: 'Pro', price: '499 €',
    features: ['2,500 plants', '3 sites', 'IoT sensors', 'Real-time VPD', 'METRC (USA)'],
  },
  {
    id: 'scale', name: 'Scale', price: '999 €',
    features: ['Unlimited plants', 'Unlimited sites', 'Multi-site dashboard', 'API access', 'Priority support'],
  },
  {
    id: 'enterprise', name: 'Enterprise', price: 'Custom',
    features: ['Everything in Scale', 'Custom SLA', 'Dedicated CSM', 'Custom integrations', 'Team training'],
  },
]

async function loadBillingStatus(): Promise<void> {
  billingLoadError.value = ''
  try {
    const { data } = await billingApi.status()
    hasStripeSubscription.value = data.hasActiveSubscription
    limits.value = data.limits
    resolvedPlan.value = data.plan
  } catch {
    billingLoadError.value = 'Unable to load billing status.'
  }
}

async function startCheckout(planId: string): Promise<void> {
  loadingCheckout.value = planId
  try {
    const { data } = await billingApi.checkout(planId)
    window.location.href = data.checkoutUrl
  } catch (e: any) {
    const message = e?.response?.data?.error ?? e?.response?.data?.detail ?? 'Error creating checkout session'
    $q.notify({ type: 'negative', message })
  } finally {
    loadingCheckout.value = null
  }
}

async function openPortal(): Promise<void> {
  loadingPortal.value = true
  try {
    const { data } = await billingApi.portal()
    window.open(data.portalUrl, '_blank')
  } catch {
    $q.notify({ type: 'negative', message: 'Unable to access the billing portal' })
  } finally {
    loadingPortal.value = false
  }
}

function contactSales(): void {
  contactForm.value = {
    name: auth.user?.email?.split('@')[0] ?? '',
    email: auth.user?.email ?? '',
    company: auth.organization?.name ?? '',
    message: '',
  }
  contactErrors.value = { name: '', email: '', message: '' }
  contactError.value = ''
  showContactDialog.value = true
}

async function submitContact(): Promise<void> {
  contactErrors.value = { name: '', email: '', message: '' }
  contactError.value = ''

  let valid = true
  if (!contactForm.value.name.trim()) { contactErrors.value.name = 'Required.'; valid = false }
  if (!contactForm.value.email.trim()) { contactErrors.value.email = 'Required.'; valid = false }
  if (!contactForm.value.message.trim()) { contactErrors.value.message = 'Required.'; valid = false }
  if (!valid) return

  contactLoading.value = true
  try {
    await billingApi.contactSales(contactForm.value)
    showContactDialog.value = false
    $q.notify({ type: 'positive', message: 'Message sent! We\'ll get back to you within one business day.' })
  } catch (e: any) {
    contactError.value = e?.response?.data?.detail ?? e?.response?.data?.error ?? 'Failed to send message.'
  } finally {
    contactLoading.value = false
  }
}

function actionLabel(planId: string): string {
  const order = ['growth', 'pro', 'scale', 'enterprise']
  const labels: Record<string, string> = { growth: 'Growth', pro: 'Pro', scale: 'Scale', enterprise: 'Enterprise' }
  const currentIndex = order.indexOf(currentPlan.value)
  const nextIndex = order.indexOf(planId)

  if (currentIndex !== -1 && nextIndex !== -1 && nextIndex < currentIndex) {
    return `Switch back to ${labels[planId] ?? planId}`
  }

  return `Switch to ${labels[planId] ?? planId}`
}

onMounted(loadBillingStatus)
</script>

<style scoped>
.billing-page { background: var(--q-color-grey-1, #f7f8fa); }
.billing-container { max-width: 960px; margin: 0 auto; padding: 32px 16px; }
.current-plan-card { border-radius: 12px; }
.plan-card { border-radius: 12px; position: relative; transition: box-shadow 0.2s; overflow: hidden; }
.plan-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
.plan-card--current { border-color: #21ba45 !important; box-shadow: 0 0 0 2px rgba(33,186,69,0.12); }
.plan-card--recommended { border-color: var(--q-primary) !important; }
.plan-badges { display: flex; gap: 8px; padding: 12px 12px 0; min-height: 34px; }
.plan-badge { position: static; }
.plan-badge--current { order: 0; }
</style>
