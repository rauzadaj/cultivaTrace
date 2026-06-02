<template>
  <div class="onboarding-shell">
    <div class="onboarding-layout">
      <section class="onboarding-hero">
        <p class="onboarding-hero__eyebrow">Tenant onboarding</p>
        <h1>Create your organization and lock in the server-side flow.</h1>
        <p class="onboarding-hero__copy">
          The organization, first admin, and Stripe customer are created in a single request. The selected
          plan serves as the commercial starting point, then activation goes through KYB and billing.
        </p>
      </section>

      <q-card flat class="onboarding-card">
        <div class="onboarding-card__header">
          <div>
            <p class="onboarding-card__eyebrow">Workspace bootstrap</p>
            <h2>Onboarding</h2>
          </div>
        </div>

        <form class="onboarding-form" @submit.prevent="submit">
          <q-input
            v-model="organizationName"
            label="Organization name"
            autocomplete="organization"
            outlined
            :error="!!fieldErrors.organizationName"
            :error-message="fieldErrors.organizationName"
            @blur="validateField('organizationName')"
            @update:model-value="clearFieldError('organizationName')"
          />
          <q-input
            v-model="email"
            label="Admin email"
            type="email"
            autocomplete="email"
            outlined
            :error="!!fieldErrors.email"
            :error-message="fieldErrors.email"
            @blur="validateField('email')"
            @update:model-value="clearFieldError('email')"
          />
          <q-input
            v-model="password"
            label="Password"
            type="password"
            autocomplete="new-password"
            outlined
            :error="!!fieldErrors.password"
            :error-message="fieldErrors.password"
            @blur="validateField('password')"
            @update:model-value="clearFieldError('password')"
          />
          <q-select
            v-model="country"
            :options="countryOptions"
            option-label="label"
            option-value="value"
            emit-value
            map-options
            label="Country"
            outlined
            :error="!!fieldErrors.country"
            :error-message="fieldErrors.country"
            @update:model-value="clearFieldError('country')"
          />
          <q-select
            v-model="plan"
            :options="planOptions"
            option-label="label"
            option-value="value"
            emit-value
            map-options
            label="Target plan"
            outlined
            :error="!!fieldErrors.plan"
            :error-message="fieldErrors.plan"
            @update:model-value="clearFieldError('plan')"
          />

          <q-banner v-if="formError" inline-actions rounded class="onboarding-error">
            {{ formError }}
          </q-banner>

          <q-banner rounded class="onboarding-note">
            The active plan remains <strong>growth</strong> until KYB and the Stripe subscription are finalized.
          </q-banner>

          <q-btn
            type="submit"
            color="primary"
            size="lg"
            unelevated
            class="full-width"
            :loading="loading"
          >
            Create organization
          </q-btn>
        </form>

        <div class="onboarding-footer">
          <span>Already have an account?</span>
          <RouterLink to="/auth">Back to sign in</RouterLink>
        </div>
      </q-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { RegistrationPlan } from '@/types/api'
import { useFormValidation, validators } from '@/composables/useFormValidation'

const router = useRouter()
const authStore = useAuthStore()

const organizationName = ref('')
const email = ref('')
const password = ref('')
const country = ref('FR')
const plan = ref<RegistrationPlan>('growth')
const loading = ref(false)
const { fieldErrors, formError, validateField, validateAll, clearFieldError, clearAllErrors, applyApiError } = useFormValidation(
  {
    get organizationName() {
      return organizationName.value
    },
    get email() {
      return email.value
    },
    get password() {
      return password.value
    },
    get country() {
      return country.value
    },
    get plan() {
      return plan.value
    },
  },
  {
    organizationName: [validators.required('Organization name required.')],
    email: [validators.required('Admin email required.'), validators.email('Invalid admin email.')],
    password: [validators.required('Password required.'), validators.minLength(8, 'Minimum 8 characters.')],
    country: [validators.required('Country required.')],
    plan: [validators.required('Plan required.')],
  },
)

const countryOptions = [
  { label: 'France', value: 'FR' },
  { label: 'Canada', value: 'CA' },
  { label: 'Germany', value: 'DE' },
  { label: 'United States', value: 'US' },
]

const planOptions = [
  { label: 'Growth — 249 €/mo', value: 'growth' },
  { label: 'Pro — 499 €/mo', value: 'pro' },
  { label: 'Scale — 999 €/mo', value: 'scale' },
]

async function submit() {
  clearAllErrors()
  loading.value = true

  try {
    organizationName.value = organizationName.value.trim()
    email.value = email.value.trim().toLowerCase()

    if (!validateAll()) {
      return
    }

    const nextPath = await authStore.registerOrganization({
      organizationName: organizationName.value,
      email: email.value,
      password: password.value,
      country: country.value,
      plan: plan.value,
    })

    await router.push(nextPath)
  } catch (caughtError) {
    applyApiError(caughtError, 'Failed to create organization.')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped lang="scss">
.onboarding-shell {
  min-height: 100vh;
  padding: 24px 16px 36px;
  background:
    radial-gradient(circle at top right, rgba(14, 116, 144, 0.12), transparent 28%),
    radial-gradient(circle at bottom left, rgba(245, 158, 11, 0.09), transparent 32%),
    #f7f8fa;
}

.onboarding-layout {
  display: grid;
  gap: 20px;
  max-width: 1120px;
  margin: 0 auto;
}

.onboarding-hero__eyebrow,
.onboarding-card__eyebrow {
  margin: 0 0 8px;
  color: #78909c;
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.onboarding-hero h1,
.onboarding-card h2 {
  margin: 0;
  font-weight: 600;
  letter-spacing: -0.03em;
}

.onboarding-hero h1 {
  max-width: 13ch;
  font-size: clamp(2rem, 7vw, 4rem);
  line-height: 1;
}

.onboarding-hero__copy {
  max-width: 56ch;
  color: #607d8b;
}

.onboarding-card {
  padding: 20px;
  border: 1px solid #e2e8f0;
  background: rgba(255, 255, 255, 0.97);
  border-radius: 20px;
}

.onboarding-form {
  display: grid;
  gap: 14px;
}

.onboarding-error {
  color: #8c2f39;
  background: #fdecec;
  border: 1px solid #f3c9cf;
}

.onboarding-note {
  color: #475569;
  background: #f8fafc;
  border: 1px solid #cbd5e1;
}

.onboarding-footer {
  display: flex;
  gap: 8px;
  margin-top: 18px;
  color: #607d8b;
}

@media (min-width: 960px) {
  .onboarding-layout {
    grid-template-columns: minmax(0, 1.05fr) minmax(420px, 0.95fr);
    align-items: center;
    min-height: calc(100vh - 72px);
  }
}
</style>
