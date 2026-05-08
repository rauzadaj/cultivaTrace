<template>
  <q-page class="billing-cancel-page">
    <div class="billing-cancel-card">
      <q-icon name="mdi-close-circle-outline" color="grey-6" size="56px" />
      <h1>Paiement annulé</h1>
      <p>Votre paiement a été annulé. Aucun montant n'a été débité.</p>
      <p class="billing-cancel-card__sub">
        Vous pouvez choisir un plan à tout moment depuis votre espace facturation.
      </p>
      <div class="billing-cancel-card__actions">
        <q-btn
          color="primary"
          unelevated
          label="Retour à la facturation"
          @click="goToBilling"
        />
        <q-btn
          flat
          color="grey-7"
          label="Aller au dashboard"
          @click="goToDashboard"
        />
      </div>
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router'

const router = useRouter()

function goToBilling(): void {
  void router.push('/billing')
}

function goToDashboard(): void {
  void router.push('/dashboard/overview')
}
</script>

<style scoped>
.billing-cancel-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  padding: 24px;
}

.billing-cancel-card {
  display: grid;
  justify-items: center;
  gap: 12px;
  max-width: 420px;
  padding: 32px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  text-align: center;
}

.billing-cancel-card h1,
.billing-cancel-card p {
  margin: 0;
}

.billing-cancel-card__sub {
  color: #718096;
  font-size: 0.9rem;
}

.billing-cancel-card__actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
  margin-top: 8px;
}
</style>
