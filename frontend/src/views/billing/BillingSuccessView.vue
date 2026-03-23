<template>
  <q-page class="billing-success-page">
    <div class="billing-success-card">
      <q-icon name="mdi-check-circle" color="positive" size="56px" />
      <h1>Abonnement activé !</h1>
      <p>Le paiement Stripe a été confirmé. Votre organisation a été mise à jour.</p>
      <q-btn color="primary" unelevated label="Accéder au dashboard" @click="goToDashboard" />
    </div>
  </q-page>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import { useAuthStore } from '@/stores/auth'
import { billingApi } from '@/services/api'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const authStore = useAuthStore()

onMounted(async () => {
  try {
    const sessionId = typeof route.query.session_id === 'string' ? route.query.session_id : ''
    if (sessionId) {
      await billingApi.confirmCheckout(sessionId)
    }
    await authStore.fetchMe()
  } catch (error) {
    console.error('Unable to refresh auth state after Stripe checkout', error)
    $q.notify({ type: 'negative', message: 'Impossible de synchroniser votre abonnement après paiement.' })
  }
})

function goToDashboard(): void {
  void router.push('/dashboard/overview')
}
</script>

<style scoped>
.billing-success-page {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 60vh;
  padding: 24px;
}

.billing-success-card {
  display: grid;
  justify-items: center;
  gap: 16px;
  max-width: 420px;
  padding: 32px;
  background: #fff;
  border: 1px solid #e2e8f0;
  border-radius: 16px;
  text-align: center;
}

.billing-success-card h1,
.billing-success-card p {
  margin: 0;
}
</style>
