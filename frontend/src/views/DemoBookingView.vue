<template>
  <div class="demo-shell">
    <div class="demo-layout">
      <section class="demo-hero">
        <div class="demo-hero__brand">
          <img src="/cultivatrace.png" alt="CultivaTrace" class="demo-hero__logo">
          <span>CultivaTrace</span>
        </div>
        <p class="demo-hero__eyebrow">Live product walkthrough</p>
        <h1>Book a demo with the CultivaTrace team.</h1>
        <p class="demo-hero__copy">
          Tell us a bit about your operation and we will tailor the walkthrough around compliance, rooms, sensors and
          billing.
        </p>

        <div class="demo-hero__chips">
          <span>Regulated workflows</span>
          <span>Ops dashboard</span>
          <span>Plan enforcement</span>
        </div>
      </section>

      <q-card flat class="demo-card">
        <div class="demo-card__header">
          <div>
            <p class="demo-card__eyebrow">Schedule request</p>
            <h2>Book a demo</h2>
          </div>
        </div>

        <template v-if="submitted">
          <q-banner rounded class="demo-success">
            Demo request received. We will reach out at <strong>{{ email }}</strong>.
          </q-banner>

          <div class="demo-card__footer">
            <RouterLink to="/" class="demo-card__link">Back to landing</RouterLink>
            <RouterLink to="/auth" class="demo-card__link">Sign in</RouterLink>
          </div>
        </template>

        <form v-else class="demo-form" @submit.prevent="submit">
          <q-input
            v-model="fullName"
            label="Full name"
            autocomplete="name"
            outlined
            :error="!!fieldErrors.fullName"
            :error-message="fieldErrors.fullName"
            @blur="validateField('fullName')"
            @update:model-value="clearFieldError('fullName')"
          />
          <q-input
            v-model="email"
            label="Work email"
            type="email"
            autocomplete="email"
            outlined
            :error="!!fieldErrors.email"
            :error-message="fieldErrors.email"
            @blur="validateField('email')"
            @update:model-value="clearFieldError('email')"
          />
          <q-input
            v-model="company"
            label="Company"
            autocomplete="organization"
            outlined
            :error="!!fieldErrors.company"
            :error-message="fieldErrors.company"
            @blur="validateField('company')"
            @update:model-value="clearFieldError('company')"
          />
          <q-select
            v-model="teamSize"
            :options="teamSizeOptions"
            emit-value
            map-options
            option-label="label"
            option-value="value"
            label="Team size"
            outlined
            :error="!!fieldErrors.teamSize"
            :error-message="fieldErrors.teamSize"
            @update:model-value="clearFieldError('teamSize')"
          />
          <q-input
            v-model="notes"
            label="What do you want to see?"
            type="textarea"
            autogrow
            outlined
            :error="!!fieldErrors.notes"
            :error-message="fieldErrors.notes"
            @blur="validateField('notes')"
            @update:model-value="clearFieldError('notes')"
          />

          <q-banner v-if="formError" inline-actions rounded class="demo-error">
            {{ formError }}
          </q-banner>

          <q-btn
            type="submit"
            color="primary"
            size="lg"
            unelevated
            class="full-width"
          >
            Request demo
          </q-btn>
        </form>

        <div v-if="!submitted" class="demo-card__footer">
          <RouterLink to="/" class="demo-card__link">Back to landing</RouterLink>
          <RouterLink to="/onboarding" class="demo-card__link">Start free instead</RouterLink>
        </div>
      </q-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { RouterLink } from 'vue-router'
import { useFormValidation, validators } from '@/composables/useFormValidation'

const fullName = ref('')
const email = ref('')
const company = ref('')
const teamSize = ref('')
const notes = ref('')
const submitted = ref(false)

const teamSizeOptions = [
  { label: '1-10', value: '1-10' },
  { label: '11-50', value: '11-50' },
  { label: '51-200', value: '51-200' },
  { label: '200+', value: '200+' },
]

const { fieldErrors, formError, validateField, validateAll, clearFieldError, clearAllErrors } = useFormValidation(
  {
    get fullName() {
      return fullName.value
    },
    get email() {
      return email.value
    },
    get company() {
      return company.value
    },
    get teamSize() {
      return teamSize.value
    },
    get notes() {
      return notes.value
    },
  },
  {
    fullName: [validators.required('Full name is required.')],
    email: [validators.required('Work email is required.'), validators.email('Enter a valid work email.')],
    company: [validators.required('Company is required.')],
    teamSize: [validators.required('Team size is required.')],
    notes: [validators.required('Tell us what you want to see.')],
  },
)

function submit() {
  clearAllErrors()
  fullName.value = fullName.value.trim()
  email.value = email.value.trim().toLowerCase()
  company.value = company.value.trim()
  notes.value = notes.value.trim()

  if (!validateAll()) {
    return
  }

  submitted.value = true
}
</script>

<style scoped lang="scss">
.demo-shell {
  min-height: 100vh;
  padding: 24px 16px 36px;
  background:
    radial-gradient(circle at top left, rgba(27, 107, 58, 0.12), transparent 28%),
    radial-gradient(circle at bottom right, rgba(245, 158, 11, 0.08), transparent 30%),
    #f7f8fa;
}

.demo-layout {
  display: grid;
  gap: 20px;
  max-width: 1120px;
  margin: 0 auto;
}

.demo-hero {
  color: #33434d;
}

.demo-hero__brand {
  display: inline-flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 18px;
  padding: 10px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(148, 163, 184, 0.16);
}

.demo-hero__brand span {
  color: #33434d;
  font-weight: 600;
}

.demo-hero__logo {
  width: 38px;
  height: 38px;
  border-radius: 10px;
  object-fit: cover;
}

.demo-hero__eyebrow,
.demo-card__eyebrow {
  margin: 0 0 8px;
  color: #78909c;
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.demo-hero h1,
.demo-card h2 {
  margin: 0;
  font-weight: 600;
  letter-spacing: -0.03em;
}

.demo-hero h1 {
  max-width: 13ch;
  font-size: clamp(2rem, 7vw, 4rem);
  line-height: 1;
}

.demo-hero__copy {
  max-width: 52ch;
  color: #607d8b;
}

.demo-hero__chips {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
  margin-top: 20px;
}

.demo-hero__chips span {
  padding: 10px 14px;
  border-radius: 999px;
  background: rgba(255, 255, 255, 0.72);
  border: 1px solid rgba(148, 163, 184, 0.16);
  color: #455a64;
}

.demo-card {
  padding: 20px;
  border: 1px solid #e2e8f0;
  background: rgba(255, 255, 255, 0.97);
  border-radius: 20px;
}

.demo-form {
  display: grid;
  gap: 14px;
}

.demo-error {
  color: #8c2f39;
  background: #fdecec;
  border: 1px solid #f3c9cf;
}

.demo-success {
  color: #1b6b3a;
  background: #e8f5ee;
  border: 1px solid #b7dfc6;
}

.demo-card__footer {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  margin-top: 18px;
}

.demo-card__link {
  color: #607d8b;
  text-decoration: none;
  font-weight: 600;
}

@media (min-width: 960px) {
  .demo-layout {
    grid-template-columns: minmax(0, 1.05fr) minmax(420px, 0.95fr);
    align-items: center;
    min-height: calc(100vh - 72px);
  }
}
</style>
