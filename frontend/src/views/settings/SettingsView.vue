<template>
  <div class="settings-view">
    <header>
      <p class="eyebrow">Parametres</p>
      <h1>Mon espace</h1>
    </header>

    <div class="settings-grid">
      <q-card class="settings-card">
        <q-card-section>
          <p class="eyebrow">Mon compte</p>
          <h2>{{ auth.user?.email || 'Compte utilisateur' }}</h2>
          <p>Gestion du mot de passe ouverte apres la beta.</p>
        </q-card-section>
      </q-card>

      <q-card class="settings-card">
        <q-card-section>
          <p class="eyebrow">Licence</p>
          <h2>Verification KYB</h2>
          <q-btn color="primary" no-caps label="Ouvrir KYB" to="/kyb" />
        </q-card-section>
      </q-card>

      <q-card class="settings-card">
        <q-card-section>
          <p class="eyebrow">Facturation</p>
          <h2>Billing</h2>
          <q-btn color="primary" no-caps label="Ouvrir billing" to="/billing" />
        </q-card-section>
      </q-card>

      <q-card class="settings-card settings-card--danger">
        <q-card-section>
          <p class="eyebrow">Danger zone</p>
          <h2>Suppression du compte</h2>
          <q-btn color="negative" no-caps label="Supprimer le compte" disable />
        </q-card-section>
      </q-card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
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
  grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
}
.settings-card h2 { margin: 0 0 10px; font-size: 1.1rem; }
.settings-card--danger { border: 1px solid #fed7d7; }
</style>
