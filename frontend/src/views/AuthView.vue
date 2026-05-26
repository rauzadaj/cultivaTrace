<template>
  <div class="auth-shell">
    <div class="auth-layout">
      <section class="auth-hero">
        <div class="auth-hero__brand">
          <img src="/cultivatrace.png" alt="CultivaTrace" class="auth-hero__logo">
          <span>CultivaTrace</span>
        </div>
        <p class="auth-hero__eyebrow">CultivaTrace</p>
        <h1>Connect field operations before opening the cockpit.</h1>
        <p class="auth-hero__copy">
          JWT authentication to access the dashboard, the append-only log, and quick actions.
        </p>

        <div class="auth-hero__chips">
          <span>Secure workflow</span>
          <span>Immutable log</span>
          <span>Real-time analytics</span>
        </div>
      </section>

      <q-card flat class="auth-card">
        <div class="auth-card__header">
          <div>
            <p class="auth-card__eyebrow">Access gateway</p>
            <h2>Sign in</h2>
          </div>
        </div>

        <form class="auth-form" @submit.prevent="submit">
          <q-input
            v-model="email"
            label="Email"
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
            autocomplete="current-password"
            outlined
            :error="!!fieldErrors.password"
            :error-message="fieldErrors.password"
            @blur="validateField('password')"
            @update:model-value="clearFieldError('password')"
          />

          <q-banner v-if="formError" inline-actions rounded class="auth-error">
            {{ formError }}
          </q-banner>

          <q-btn
            type="submit"
            color="primary"
            size="lg"
            unelevated
            class="full-width"
            :loading="loading"
          >
            Sign in
          </q-btn>
        </form>

        <div class="auth-footer auth-footer--link">
          <span>New organization?</span>
          <RouterLink to="/onboarding">Start onboarding</RouterLink>
        </div>
      </q-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '../stores/auth'
import { useFormValidation, validators } from '@/composables/useFormValidation'
import { consumeSessionExpiredFlag } from '@/services/authSession'

const $q = useQuasar()
const route = useRoute()
const router = useRouter()
const authStore = useAuthStore()

onMounted(() => {
  if (consumeSessionExpiredFlag()) {
    $q.notify({
      type: 'warning',
      message: 'Your session has expired. Please sign in again.',
      timeout: 6000,
    })
  }
})

const email = ref(authStore.user?.email ?? '')
const password = ref('')
const loading = ref(false)
const { fieldErrors, formError, validateField, validateAll, clearFieldError, clearAllErrors, applyApiError } = useFormValidation(
  {
    get email() {
      return email.value
    },
    get password() {
      return password.value
    },
  },
  {
    email: [validators.required('Email required.'), validators.email('Invalid email.')],
    password: [validators.required('Password required.')],
  },
)

async function submit() {
  clearAllErrors()
  loading.value = true

  try {
    const normalizedEmail = email.value.trim().toLowerCase()
    email.value = normalizedEmail

    if (!validateAll()) {
      return
    }

    await authStore.login(normalizedEmail, password.value)
    await router.push(resolveRedirectTarget())
  } catch (caughtError) {
    applyApiError(caughtError, 'Authentication failed.')
  } finally {
    loading.value = false
  }
}

function resolveRedirectTarget() {
  return typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard/overview'
}
</script>

<style scoped lang="scss">
@use '../css/breakpoints.sass' as bp;

.auth-shell {
  background:
    radial-gradient(ellipse 70% 50% at 0% 0%, rgba(22, 163, 74, 0.05) 0%, transparent 60%),
    #F7F8FA;
  min-height: 100vh;
}

.auth-layout {
  min-height: 100vh;
  display: grid;
  gap: 24px;
  align-items: start;
  padding: 24px 20px 40px;
  min-width: 0;
}

.auth-hero {
  color: var(--ct-text-1);
}

.auth-hero__brand {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 24px;
  padding: 8px 12px;
  border-radius: 999px;
  background: #FFFFFF;
  border: 1px solid var(--ct-border);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.auth-hero__brand span {
  color: var(--ct-text-1);
  font-weight: 600;
  font-size: 0.875rem;
  letter-spacing: -0.01em;
}

.auth-hero__logo {
  width: 26px;
  height: 26px;
  border-radius: 7px;
  object-fit: cover;
}

.auth-hero__eyebrow,
.auth-card__eyebrow {
  margin: 0 0 10px;
  color: var(--ct-accent);
  font-size: 0.68rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  font-weight: 700;
}

.auth-hero h1,
.auth-card h2 {
  margin: 0;
  font-weight: 700;
  letter-spacing: -0.04em;
}

.auth-hero h1 {
  max-width: 14ch;
  font-size: clamp(1.875rem, 6vw, 4rem);
  line-height: 1.05;
  overflow-wrap: anywhere;
  color: var(--ct-text-1);
}

.auth-hero__copy {
  max-width: 44ch;
  color: var(--ct-text-2);
  line-height: 1.65;
  margin-top: 16px;
  font-size: 0.9375rem;
}

.auth-hero__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 28px;
}

.auth-hero__chips span {
  padding: 7px 14px;
  border-radius: 999px;
  background: #FFFFFF;
  border: 1px solid var(--ct-border);
  color: var(--ct-text-2);
  font-size: 0.8125rem;
  font-weight: 500;
  box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
}

.auth-card {
  min-width: 0;
  padding: 28px;
  border: 1px solid var(--ct-border);
  background: #FFFFFF;
  border-radius: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05), 0 0 0 1px rgba(0, 0, 0, 0.03);
}

.auth-card__header {
  display: grid;
  gap: 18px;
  margin-bottom: 24px;
}

.auth-card h2 {
  font-size: 1.5rem;
  color: var(--ct-text-1);
}

.auth-form {
  display: grid;
  gap: 16px;
}

.auth-error {
  color: #991B1B;
  background: #FEF2F2;
  border: 1px solid #FECACA;
  border-radius: 8px;
}

.auth-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 20px;
  color: var(--ct-text-2);
  font-size: 0.875rem;
}

.auth-footer a {
  color: var(--ct-accent);
  text-decoration: none;
  font-weight: 500;
}

.auth-footer a:hover {
  text-decoration: underline;
}

@include bp.mobile {
  .auth-shell {
    overflow-x: clip;
  }
}

@include bp.desktop {
  .auth-layout {
    grid-template-columns: minmax(0, 1.2fr) minmax(400px, 0.8fr);
    align-items: center;
    padding: 48px;
  }

  .auth-card__header {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
  }
}
</style>
