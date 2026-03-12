<template>
  <v-app class="auth-shell">
    <div class="auth-layout">
      <section class="auth-hero">
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

      <v-card class="auth-card" rounded="xl" elevation="8">
        <div class="auth-card__header">
          <div>
            <p class="auth-card__eyebrow">Access gateway</p>
            <h2>{{ mode === 'login' ? 'Connexion' : 'Creation de compte' }}</h2>
          </div>

          <v-btn-toggle v-model="mode" mandatory color="primary" density="comfortable">
            <v-btn value="login">Login</v-btn>
            <v-btn value="signup">Sign up</v-btn>
          </v-btn-toggle>
        </div>

        <form class="auth-form" @submit.prevent="submit">
          <v-text-field
            v-model="apiBaseUrl"
            label="API base URL"
            variant="outlined"
            density="comfortable"
            hide-details
          />
          <v-text-field
            v-model="email"
            label="Email"
            type="email"
            autocomplete="email"
            variant="outlined"
            density="comfortable"
            hide-details
          />
          <v-text-field
            v-model="password"
            :label="mode === 'login' ? 'Mot de passe' : 'Mot de passe (8+ caracteres)'"
            type="password"
            :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
            variant="outlined"
            density="comfortable"
            hide-details
          />
          <v-text-field
            v-if="mode === 'signup'"
            v-model="confirmPassword"
            label="Confirmer le mot de passe"
            type="password"
            autocomplete="new-password"
            variant="outlined"
            density="comfortable"
            hide-details
          />

          <v-alert v-if="error" type="error" variant="tonal">
            {{ error }}
          </v-alert>

          <v-btn
            type="submit"
            color="primary"
            size="large"
            variant="flat"
            block
            :loading="loading"
          >
            {{ mode === 'login' ? 'Se connecter' : 'Creer le compte' }}
          </v-btn>
        </form>

        <div class="auth-footer">
          <span>Compte de demo local:</span>
          <strong>demo@cultivatrace.local / demo123</strong>
        </div>
      </v-card>
    </div>
  </v-app>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ApiError, login, register } from '../lib/api'
import { useUserStore } from '../stores/useUserStore'

type AuthMode = 'login' | 'signup'

const route = useRoute()
const router = useRouter()
const userStore = useUserStore()

const mode = ref<AuthMode>('login')
const email = ref(userStore.userEmail)
const password = ref('')
const confirmPassword = ref('')
const loading = ref(false)
const error = ref('')
const apiBaseUrl = computed({
  get: () => userStore.apiBaseUrl,
  set: (value: string) => userStore.setApiBaseUrl(value),
})

async function submit() {
  error.value = ''
  loading.value = true

  try {
    const normalizedEmail = email.value.trim().toLowerCase()

    if (!normalizedEmail) {
      throw new Error('Email requis.')
    }

    if (!password.value.trim()) {
      throw new Error('Mot de passe requis.')
    }

    if (mode.value === 'signup') {
      if (password.value !== confirmPassword.value) {
        throw new Error('Les mots de passe ne correspondent pas.')
      }

      await register(normalizedEmail, password.value)
    }

    const payload = await login(normalizedEmail, password.value)

    userStore.setSession(payload.token, normalizedEmail)
    await router.push(resolveRedirectTarget())
  } catch (caughtError) {
    error.value = caughtError instanceof ApiError || caughtError instanceof Error
      ? caughtError.message
      : 'Echec de l’authentification.'
  } finally {
    loading.value = false
  }
}

function resolveRedirectTarget() {
  return typeof route.query.redirect === 'string' ? route.query.redirect : '/dashboard/overview'
}
</script>

<style scoped>
:global(body) {
  margin: 0;
  font-family: Roboto, "Helvetica Neue", sans-serif;
  background: #eef2f5;
}

.auth-shell {
  background:
    radial-gradient(circle at top left, rgba(38, 166, 154, 0.18), transparent 28%),
    radial-gradient(circle at bottom right, rgba(66, 165, 245, 0.12), transparent 30%),
    #eef2f5;
}

.auth-layout {
  min-height: 100vh;
  display: grid;
  gap: 24px;
  align-items: center;
  padding: 24px;
}

.auth-hero {
  color: #33434d;
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
  font-weight: 500;
  letter-spacing: -0.03em;
}

.auth-hero h1 {
  max-width: 12ch;
  font-size: clamp(2.2rem, 6vw, 4.5rem);
  line-height: 0.95;
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
  padding: 24px;
  border: 1px solid rgba(148, 163, 184, 0.2);
  background: rgba(255, 255, 255, 0.96);
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

.auth-footer {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin-top: 18px;
  color: #607d8b;
  font-size: 0.92rem;
}

@media (min-width: 960px) {
  .auth-layout {
    grid-template-columns: minmax(0, 1.15fr) minmax(380px, 0.85fr);
    padding: 40px;
  }

  .auth-card__header {
    grid-template-columns: minmax(0, 1fr) auto;
    align-items: start;
  }
}
</style>
