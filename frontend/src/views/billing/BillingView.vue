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
                  label="Actif"
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

const currentPlan = computed(() => resolvedPlan.value ?? auth.organization?.plan ?? 'starter')
const recommendedPlan = computed(() => 'pro')

const planLabel = computed(() => {
  const labels: Record<string, string> = {
    starter: 'Starter — 79 €/mois',
    pro: 'Pro — 249 €/mois',
    business: 'Business — 599 €/mois',
    enterprise: 'Enterprise',
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
    { key: 'rooms',  label: 'Rooms',  current: limits.value.rooms.current,  max: limits.value.rooms.max  },
    { key: 'users',  label: 'Users',  current: limits.value.users.current,  max: limits.value.users.max  },
  ]
})

const plans = [
  {
    id: 'starter', name: 'Starter', price: '79 €',
    features: ['200 plants', '2 rooms', '3 users', 'PDF reports', 'Audit trail'],
  },
  {
    id: 'pro', name: 'Pro', price: '249 €',
    features: ['1,500 plants', '10 rooms', '15 users', 'IoT sensors', 'Real-time VPD', 'METRC (USA)'],
  },
  {
    id: 'business', name: 'Business', price: '599 €',
    features: ['Unlimited plants', 'Unlimited rooms', 'Unlimited users', 'Multi-site', 'API access', 'Priority support'],
  },
  {
    id: 'enterprise', name: 'Enterprise', price: 'Custom',
    features: ['Everything in Business', 'Dedicated SLA', 'Dedicated CSM', 'Custom integrations', 'Team training'],
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
  window.location.href = 'mailto:jonathan@rauzada.me?subject=Enterprise CultivaTrace'
}

function actionLabel(planId: string): string {
  const order = ['starter', 'pro', 'business', 'enterprise']
  const currentIndex = order.indexOf(currentPlan.value)
  const nextIndex = order.indexOf(planId)

  if (currentIndex !== -1 && nextIndex !== -1 && nextIndex < currentIndex) {
    return `Switch back to ${planId === 'starter' ? 'Starter' : planId}`
  }

  return `Switch to ${planId === 'starter' ? 'Starter' : planId === 'pro' ? 'Pro' : planId === 'business' ? 'Business' : 'Enterprise'}`
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
