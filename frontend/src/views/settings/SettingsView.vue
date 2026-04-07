<template>
  <div class="settings-view">
    <header>
      <p class="eyebrow">Tenant admin</p>
      <h1>Administration organisation</h1>
    </header>

    <div class="settings-grid">
      <q-card class="settings-card">
        <q-card-section>
          <p class="eyebrow">Organisation</p>
          <h2>Paramètres</h2>

          <form class="settings-form" @submit.prevent="saveSettings">
            <q-input v-model="form.name" label="Nom" outlined />
            <q-input v-model="form.contactEmail" label="Email contact" type="email" outlined />
            <q-input
              v-model="configJson"
              label="Config JSON"
              type="textarea"
              autogrow
              outlined
            />
            <q-btn color="primary" no-caps :loading="savingSettings" type="submit" label="Enregistrer" />
          </form>
        </q-card-section>
      </q-card>

      <q-card class="settings-card">
        <q-card-section>
          <p class="eyebrow">Abonnement</p>
          <h2>{{ settings?.plan ?? auth.organization?.plan ?? 'starter' }}</h2>
          <div class="metric-list">
            <div>
              <span>Licence</span>
              <strong>{{ settings?.licenseStatus ?? auth.organization?.licenseStatus ?? 'pending' }}</strong>
            </div>
            <div>
              <span>Contact</span>
              <strong>{{ settings?.contactEmail || 'non renseigné' }}</strong>
            </div>
            <div>
              <span>Stripe</span>
              <strong>{{ settings?.stripeCustomerId ? 'lié' : 'non lié' }}</strong>
            </div>
          </div>
          <q-btn color="primary" no-caps label="Ouvrir billing" to="/billing" />
        </q-card-section>
      </q-card>

      <q-card class="settings-card">
        <q-card-section>
          <p class="eyebrow">Membres</p>
          <h2>{{ members.length }} membre(s)</h2>

          <div class="settings-list">
            <div v-for="member in members" :key="member.id" class="settings-list__item">
              <div>
                <strong>{{ member.email }}</strong>
                <p>{{ member.roles.join(', ') }}</p>
              </div>
              <q-badge color="primary" outline>
                {{ member.accountStatus }}
              </q-badge>
            </div>
          </div>
        </q-card-section>
      </q-card>

      <q-card class="settings-card">
        <q-card-section>
          <p class="eyebrow">Invitations</p>
          <h2>Inviter un membre</h2>

          <form class="settings-form" @submit.prevent="inviteMember">
            <q-input v-model="inviteEmail" label="Email invité" type="email" outlined />
            <q-select
              v-model="inviteRole"
              :options="inviteRoleOptions"
              option-label="label"
              option-value="value"
              emit-value
              map-options
              label="Rôle"
              outlined
            />
            <q-btn color="primary" no-caps :loading="inviting" type="submit" label="Envoyer l’invitation" />
          </form>

          <div class="settings-list">
            <div v-for="invitation in invitations" :key="invitation.id" class="settings-list__item">
              <div>
                <strong>{{ invitation.email }}</strong>
                <p>{{ invitation.roles.join(', ') }}</p>
              </div>
              <q-badge color="secondary" outline>
                expire {{ formatDate(invitation.expiresAt) }}
              </q-badge>
            </div>
          </div>
        </q-card-section>
      </q-card>
    </div>

    <q-banner v-if="error" rounded class="settings-error">
      {{ error }}
    </q-banner>

    <q-banner v-if="success" rounded class="settings-success">
      {{ success }}
    </q-banner>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { organizationAdminApi } from '@/services/api'
import { useAuthStore } from '@/stores/auth'
import type { OrganizationInvitation, OrganizationMember, OrganizationSettingsResponse } from '@/types/api'

const auth = useAuthStore()

const settings = ref<OrganizationSettingsResponse | null>(null)
const members = ref<OrganizationMember[]>([])
const invitations = ref<OrganizationInvitation[]>([])
const error = ref('')
const success = ref('')
const savingSettings = ref(false)
const inviting = ref(false)

const form = reactive({
  name: '',
  contactEmail: '',
})
const configJson = ref('{}')
const inviteEmail = ref('')
const inviteRole = ref<'ROLE_ORG_ADMIN' | 'ROLE_ORG_USER'>('ROLE_ORG_USER')

const inviteRoleOptions: Array<{ label: string; value: 'ROLE_ORG_ADMIN' | 'ROLE_ORG_USER' }> = [
  { label: 'Org user', value: 'ROLE_ORG_USER' },
  { label: 'Org admin', value: 'ROLE_ORG_ADMIN' },
]

onMounted(async () => {
  await Promise.all([
    loadSettings(),
    loadMembers(),
    loadInvitations(),
  ])
})

async function loadSettings() {
  const { data } = await organizationAdminApi.settings()
  settings.value = data
  form.name = data.name
  form.contactEmail = data.contactEmail ?? ''
  configJson.value = JSON.stringify(data.config ?? {}, null, 2)
}

async function loadMembers() {
  const { data } = await organizationAdminApi.members()
  members.value = data.members
}

async function loadInvitations() {
  const { data } = await organizationAdminApi.invitations()
  invitations.value = data.invitations
}

async function saveSettings() {
  error.value = ''
  success.value = ''
  savingSettings.value = true

  try {
    const parsedConfig = JSON.parse(configJson.value || '{}') as Record<string, unknown>
    const { data } = await organizationAdminApi.updateSettings({
      name: form.name.trim(),
      contactEmail: form.contactEmail.trim() || null,
      config: parsedConfig,
    })
    settings.value = data
    auth.user = auth.user
      ? {
          ...auth.user,
          organization: {
            ...(auth.user.organization ?? {
              id: data.id,
              plan: data.plan,
              licenseStatus: data.licenseStatus,
            }),
            id: data.id,
            name: data.name,
            plan: data.plan,
            licenseStatus: data.licenseStatus,
            contactEmail: data.contactEmail ?? undefined,
            config: data.config,
            country: data.country,
          },
        }
      : auth.user
    success.value = 'Paramètres organisation mis à jour.'
  } catch (caughtError) {
    error.value = caughtError instanceof Error ? caughtError.message : 'Impossible de sauvegarder.'
  } finally {
    savingSettings.value = false
  }
}

async function inviteMember() {
  error.value = ''
  success.value = ''
  inviting.value = true

  try {
    if (!inviteEmail.value.trim()) {
      throw new Error('Email invité requis.')
    }

    await organizationAdminApi.invite({
      email: inviteEmail.value.trim().toLowerCase(),
      role: inviteRole.value,
    })
    inviteEmail.value = ''
    inviteRole.value = 'ROLE_ORG_USER'
    success.value = 'Invitation envoyée.'
    await loadInvitations()
  } catch (caughtError) {
    error.value = caughtError instanceof Error ? caughtError.message : 'Impossible d’envoyer l’invitation.'
  } finally {
    inviting.value = false
  }
}

function formatDate(value: string) {
  return new Date(value).toLocaleDateString('fr-FR')
}
</script>

<style scoped lang="scss">
.settings-view { display: grid; gap: 16px; }
.settings-view h1 { margin: 0; font-size: 1.75rem; }
.eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}
.settings-grid {
  display: grid;
  gap: 16px;
  grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
}
.settings-card h2 { margin: 0 0 12px; font-size: 1.1rem; }
.settings-form {
  display: grid;
  gap: 12px;
}
.metric-list, .settings-list {
  display: grid;
  gap: 12px;
}
.metric-list div,
.settings-list__item {
  display: flex;
  align-items: start;
  justify-content: space-between;
  gap: 12px;
}
.settings-list__item p {
  margin: 4px 0 0;
  color: #718096;
  font-size: 0.9rem;
}
.settings-error {
  color: #8c2f39;
  background: #fdecec;
}
.settings-success {
  color: #166534;
  background: #ecfdf5;
}
</style>
