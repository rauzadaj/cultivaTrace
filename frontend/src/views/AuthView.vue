<template>
  <div class="auth-shell">
    <div class="auth-layout">
      <section class="auth-hero">
        <div class="auth-hero__brand">
          <img src="/cultivatrace.png" alt="CultivaTrace" class="auth-hero__logo">
          <span>CultivaTrace</span>
        </div>
        <p class="auth-hero__eyebrow">CultivaTrace</p>
        <h1>Connecte l’operation terrain avant d’ouvrir le cockpit.</h1>
        <p class="auth-hero__copy">
          Authentification JWT pour acceder au dashboard, au journal append-only et aux quick actions.
        </p>

        <div class="auth-hero__chips">
          <span>Workflow securise</span>
          <span>Journal immutable</span>
          <span>Analytics temps reel</span>
        </div>
      </section>

      <q-card flat class="auth-card">
        <div class="auth-card__header">
          <div>
            <p class="auth-card__eyebrow">Access gateway</p>
            <h2>Connexion</h2>
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
            label="Mot de passe"
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
            Se connecter
          </q-btn>
        </form>

        <div class="auth-footer auth-footer--link">
          <span>Nouvelle organisation ?</span>
          <RouterLink to="/onboarding">Lancer l’onboarding</RouterLink>
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
      message: 'Votre session a expiré. Veuillez vous reconnecter.',
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
    email: [validators.required('Email requis.'), validators.email('Email invalide.')],
    password: [validators.required('Mot de passe requis.')],
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
    applyApiError(caughtError, 'Echec de l’authentification.')
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
    radial-gradient(circle at top left, rgba(27, 107, 58, 0.12), transparent 28%),
    radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.08), transparent 30%),
    #f7f8fa;
}

.auth-layout {
  min-height: 100vh;
  display: grid;
  gap: 20px;
  align-items: start;
  padding: 20px 16px 32px;
  min-width: 0;
}

.auth-hero {
  color: #33434d;
}

.auth-hero__brand {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
  padding: 10px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(148, 163, 184, 0.16);
}

.auth-hero__brand span {
  color: #33434d;
  font-weight: 600;
}

.auth-hero__logo {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  object-fit: cover;
}

.auth-hero__eyebrow,
.auth-card__eyebrow {
  margin: 0 0 8px;
  color: #78909c;
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.auth-hero h1,
.auth-card h2 {
  margin: 0;
  font-weight: 600;
  letter-spacing: -0.03em;
}

.auth-hero h1 {
  max-width: 14ch;
  font-size: clamp(2rem, 8vw, 4.5rem);
  line-height: 1;
  overflow-wrap: anywhere;
}

.auth-hero__copy {
  max-width: 44ch;
  color: #607d8b;
}

.auth-hero__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 20px;
}

.auth-hero__chips span {
  padding: 10px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(148, 163, 184, 0.16);
  color: #455a64;
}

.auth-card {
  min-width: 0;
  padding: 20px;
  border: 1px solid #e2e8f0;
  background: rgba(255, 255, 255, 0.96);
  border-radius: 20px;
}

.auth-card__header {
  display: grid;
  gap: 18px;
  margin-bottom: 20px;
}

.auth-form {
  display: grid;
  gap: 14px;
}

.auth-error {
  color: #8c2f39;
  background: #fdecec;
  border: 1px solid #f3c9cf;
}

.auth-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 18px;
  color: #607d8b;
  font-size: 0.92rem;
}

@include bp.mobile {
  .auth-shell {
    overflow-x: clip;
  }
}

@include bp.desktop {
  .auth-layout {
    grid-template-columns: minmax(0, 1.15fr) minmax(380px, 0.85fr);
    align-items: center;
    padding: 40px;
  }

  .auth-card__header {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
  }
}
</style>
