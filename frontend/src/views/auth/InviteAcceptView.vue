<template>
  <div class="invite-shell">
    <q-card flat class="invite-card">
      <div class="invite-card__header">
        <p class="eyebrow">Organization invite</p>
        <h1>Activate invitation</h1>
      </div>

      <form class="invite-form" @submit.prevent="submit">
        <q-input
          v-model="password"
          label="New password"
          type="password"
          autocomplete="new-password"
          outlined
          :error="!!fieldErrors.password"
          :error-message="fieldErrors.password"
          @blur="validateField('password')"
          @update:model-value="clearFieldError('password')"
        />

        <q-banner v-if="formError" rounded class="invite-error">
          {{ formError }}
        </q-banner>

        <q-banner v-if="successMessage" rounded class="invite-success">
          {{ successMessage }}
        </q-banner>

        <q-btn
          type="submit"
          color="primary"
          unelevated
          :loading="loading"
        >
          Activate access
        </q-btn>
      </form>
    </q-card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { authApi } from '@/services/api'
import { useFormValidation, validators } from '@/composables/useFormValidation'

const route = useRoute()
const router = useRouter()

const password = ref('')
const loading = ref(false)
const successMessage = ref('')
const { fieldErrors, formError, validateField, validateAll, clearFieldError, clearAllErrors, setFormError, applyApiError } = useFormValidation(
  {
    get password() {
      return password.value
    },
  },
  {
    password: [validators.required('Password required.'), validators.minLength(8, 'Minimum 8 characters.')],
  },
)

async function submit() {
  clearAllErrors()
  successMessage.value = ''
  loading.value = true

  try {
    const token = String(route.query.token ?? '').trim()

    if (!token) {
      setFormError('Invalid or expired invitation.')
      return
    }

    if (!validateAll()) {
      return
    }

    const { data } = await authApi.acceptInvitation(token, password.value)
    successMessage.value = `Invitation activated for ${data.email}. Sign in to continue.`
    window.setTimeout(() => {
      void router.push('/auth')
    }, 1200)
  } catch (caughtError) {
    applyApiError(caughtError, ‘Failed to activate invitation.’)
  } finally {
    loading.value = false
  }
}
</script>

<style scoped lang="scss">
.invite-shell {
  min-height: 100vh;
  display: grid;
  place-items: center;
  padding: 24px 16px;
  background: #f7f8fa;
}
.invite-card {
  width: min(100%, 480px);
  padding: 24px;
  border-radius: 20px;
  border: 1px solid #e2e8f0;
  background: rgba(255, 255, 255, 0.98);
}
.eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}
.invite-card h1 {
  margin: 0 0 16px;
  font-size: 1.8rem;
}
.invite-form {
  display: grid;
  gap: 14px;
}
.invite-error {
  color: #8c2f39;
  background: #fdecec;
}
.invite-success {
  color: #166534;
  background: #ecfdf5;
}
</style>
