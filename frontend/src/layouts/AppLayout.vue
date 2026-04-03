<template>
  <q-layout view="hHh Lpr lFf" class="app-shell">
    <template v-if="restrictedToKyb">
      <q-page-container class="app-page-container">
        <q-page class="app-page">
          <div class="app-page__inner">
            <RouterView />
          </div>
        </q-page>
      </q-page-container>
    </template>

    <template v-else>
    <div v-if="!offlineState.isOnline" class="offline-banner">
      Hors ligne — {{ offlineState.pendingCount }} actions en attente de synchronisation
    </div>

    <q-banner v-if="showLicenseBanner" inline-actions class="license-banner">
      <div>
        Licence {{ authStore.organization?.licenseStatus === 'pending' ? 'en attente de verification' : 'suspendue' }}.
        Completez votre dossier KYB pour conserver l'acces complet.
      </div>
      <template #action>
        <q-btn flat color="primary" label="Verifier ma licence" to="/kyb" />
      </template>
    </q-banner>

    <q-header class="app-header">
      <q-toolbar class="app-header__toolbar" :class="{ 'app-header__toolbar--landscape': isMobile && isLandscape }">
        <q-btn
          v-if="isMobile"
          flat
          round
          class="app-header__menu"
          @click="drawerOpen = !drawerOpen"
        >
          <q-icon name="mdi-menu" size="22px" />
        </q-btn>

        <div class="app-header__brand" :class="{ 'app-header__brand--centered': showMobileFooter }">
          <img src="/cultivatrace.png" alt="CultivaTrace" class="app-header__logo">
          <div class="app-header__copy">
            <span v-if="isMobile && isPortrait" class="app-header__kicker">{{ mobileKicker }}</span>
            <strong>{{ headerTitle }}</strong>
            <span v-if="!isMobile">{{ breadcrumb }}</span>
          </div>
        </div>

        <template v-if="isDesktop">
          <c-input class="app-header__search" dense standout="bg-white text-dark" placeholder="Recherche globale">
            <template #prepend>
              <q-icon name="mdi-magnify" size="18px" />
            </template>
          </c-input>
        </template>

        <q-space />

        <q-btn v-if="showFab" flat round class="app-header__action" @click="handleFab">
          <q-icon :name="fabIcon" size="22px" />
        </q-btn>

        <q-btn flat round class="app-header__notify" @click="openNotifications">
          <q-icon name="mdi-bell-outline" size="22px" />
          <q-badge v-if="alertCount > 0" color="negative" floating rounded />
        </q-btn>

        <q-btn v-if="!isMobile" flat round icon="mdi-account-circle-outline">
          <q-menu anchor="bottom right" self="top right">
            <q-list style="min-width: 180px">
              <q-item>
                <q-item-section>{{ userIdentity }}</q-item-section>
              </q-item>
              <q-separator />
              <q-item clickable @click="logout">
                <q-item-section>Logout</q-item-section>
              </q-item>
            </q-list>
          </q-menu>
        </q-btn>
      </q-toolbar>
    </q-header>

    <q-drawer
      v-model="drawerOpen"
      :mini="drawerMini"
      :width="drawerWidth"
      :mini-width="60"
      :overlay="isMobile"
      :behavior="isMobile ? 'mobile' : 'desktop'"
      bordered
      class="app-drawer"
      :show-if-above="!isMobile"
      @mouseover="drawerHover = true"
      @mouseleave="drawerHover = false"
    >
      <div class="app-drawer__content">
        <nav class="app-nav">
          <RouterLink
            v-for="item in desktopNavigation"
            :key="item.to"
            :to="item.to"
            class="app-nav__item"
            active-class="app-nav__item--active"
            @click="handleNavigationClick"
          >
            <q-icon :name="item.icon" size="22px" />
            <span v-if="showDrawerLabels">{{ item.label }}</span>
          </RouterLink>
        </nav>

        <div v-if="isMobile" class="app-drawer__footer">
          <div class="app-drawer__user">
            <strong>{{ userDisplayName }}</strong>
            <span>{{ authStore.user?.email || 'operateur@cultivatrace.local' }}</span>
          </div>
          <c-btn variant="ghost" class="full-width" @click="logout">Logout</c-btn>
        </div>
      </div>
    </q-drawer>

    <q-page-container class="app-page-container">
      <q-page class="app-page">
        <div class="app-page__inner">
          <RouterView />
        </div>
      </q-page>
    </q-page-container>

    <q-footer v-if="showMobileFooter" class="app-footer">
      <q-tabs
        dense
        indicator-color="transparent"
        active-color="primary"
        class="bottom-tabs"
      >
        <q-route-tab
          v-for="item in mobileNavigation"
          :key="item.to"
          :to="item.to"
          :icon="item.icon"
          :label="item.label"
          no-caps
        />
      </q-tabs>
    </q-footer>

    <q-dialog v-model="notificationsOpen" :position="isMobile ? 'bottom' : 'right'" :maximized="isMobile" :full-width="isTablet">
      <q-card class="notifications-panel">
        <div class="notifications-panel__header">
          <div>
            <p class="notifications-panel__eyebrow">Notifications</p>
            <h2>Alertes actives</h2>
          </div>
          <q-btn flat round icon="mdi-close" @click="notificationsOpen = false" />
        </div>

        <div v-if="appAlerts.length" class="notifications-panel__list">
          <article v-for="alert in appAlerts" :key="alert.id" class="notifications-panel__item">
            <div class="notifications-panel__item-top">
              <span class="notifications-panel__severity" :class="`notifications-panel__severity--${alert.severity}`">{{ alert.severity }}</span>
              <strong>{{ alert.title }}</strong>
            </div>
            <p>{{ alert.message }}</p>
            <small v-if="alert.context">{{ alert.context }}</small>
          </article>
        </div>

        <div v-else class="notifications-panel__empty">
          <q-icon name="mdi-check-circle-outline" size="28px" />
          <strong>Aucune alerte en attente</strong>
        </div>
      </q-card>
    </q-dialog>

    <q-dialog v-model="createPlantOpen" :position="isMobile ? 'bottom' : 'standard'" :maximized="isMobile" :full-width="isTablet">
      <q-card class="action-dialog">
        <div class="action-dialog__header">
          <div>
            <p class="notifications-panel__eyebrow">Action rapide</p>
            <h2>Nouveau plant</h2>
          </div>
          <q-btn flat round icon="mdi-close" @click="createPlantOpen = false" />
        </div>

        <div class="action-dialog__body">
          <c-input
            v-model="createPlantForm.room"
            select
            :items="roomOptions"
            option-label="label"
            option-value="value"
            emit-value
            map-options
            label="Salle"
            stack-label
          />
          <c-input
            v-model="createPlantForm.strain"
            select
            :items="strainOptions"
            option-label="label"
            option-value="value"
            emit-value
            map-options
            label="Genetique"
            stack-label
          />
          <c-input v-model="createPlantForm.rfidTag" label="RFID / Etiquette" />
          <c-input v-model="createPlantForm.germinatedAt" label="Date de germination" type="date" />
        </div>

        <div class="action-dialog__footer">
          <c-btn variant="ghost" @click="createPlantOpen = false">Annuler</c-btn>
          <c-btn variant="primary" :loading="actionPending" @click="submitCreatePlant">Creer</c-btn>
        </div>
      </q-card>
    </q-dialog>

    <q-dialog v-model="stageDialogOpen" :position="isMobile ? 'bottom' : 'standard'" :maximized="isMobile" :full-width="isTablet">
      <q-card class="action-dialog">
        <div class="action-dialog__header">
          <div>
            <p class="notifications-panel__eyebrow">Action rapide</p>
            <h2>Changer le stade</h2>
          </div>
          <q-btn flat round icon="mdi-close" @click="stageDialogOpen = false" />
        </div>

        <div class="action-dialog__body action-dialog__body--chips">
            <p v-if="!stageOptions.length" class="action-dialog__hint">Aucune transition disponible pour ce plant.</p>
            <q-chip
              v-for="option in stageOptions"
              :key="option.label"
              clickable
              outline
            color="primary"
            class="action-dialog__chip"
            @click="submitStageChange(option.transition)"
          >
            {{ option.label }}
          </q-chip>
        </div>

        <div v-if="stageOptions.length" class="action-dialog__footer">
          <c-btn variant="ghost" @click="stageDialogOpen = false">Fermer</c-btn>
        </div>
      </q-card>
    </q-dialog>

    <q-dialog v-model="confirmDialog.open" persistent :maximized="isMobile" :full-width="isTablet">
      <q-card class="action-dialog action-dialog--confirm">
        <div class="action-dialog__header">
          <div>
            <p class="notifications-panel__eyebrow">Confirmation</p>
            <h2>{{ confirmDialog.title }}</h2>
          </div>
        </div>

        <div class="action-dialog__body">
          <p class="action-dialog__hint">{{ confirmDialog.message }}</p>
        </div>

        <div class="action-dialog__footer">
          <c-btn variant="ghost" @click="closeConfirmDialog">Annuler</c-btn>
          <c-btn variant="danger" :loading="actionPending" @click="runConfirmedAction">Confirmer</c-btn>
        </div>
      </q-card>
    </q-dialog>
    </template>
  </q-layout>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref, watch } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import CBtn from '../components/ui/CBtn.vue'
import CInput from '../components/ui/CInput.vue'
import { useOfflineQueue } from '../composables/useOfflineQueue'
import { useAuthStore } from '../stores/auth'
import { usePlantsStore } from '../stores/plants'
import type { AlertItem, Plant, PlantStage } from '../types/api'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const plantsStore = usePlantsStore()
const authStore = useAuthStore()
const offlineState = useOfflineQueue()

const drawerOpen = ref(true)
const drawerHover = ref(false)
const notificationsOpen = ref(false)
const createPlantOpen = ref(false)
const stageDialogOpen = ref(false)
const actionPending = ref(false)
const confirmedAction = ref<null | (() => Promise<void>)>(null)
const createPlantForm = reactive({
  room: '',
  strain: '',
  rfidTag: '',
  germinatedAt: new Date().toISOString().slice(0, 10),
})
const confirmDialog = reactive({
  open: false,
  title: '',
  message: '',
})

const isMobile = computed(() => $q.screen.width < 768)
const isTablet = computed(() => $q.screen.width >= 768 && $q.screen.width < 1024)
const isDesktop = computed(() => $q.screen.width >= 1024)
const isPortrait = computed(() => $q.screen.height > $q.screen.width)
const isLandscape = computed(() => $q.screen.width >= $q.screen.height)
const showMobileFooter = computed(() => isMobile.value && isPortrait.value)
const showFab = computed(() => route.name === 'plants-list' || route.name === 'plant-detail')
const drawerMini = computed(() => isTablet.value && isPortrait.value)
const drawerWidth = computed(() => {
  if (isDesktop.value) return 240
  if (isTablet.value && isPortrait.value) return 60
  return 220
})
const showDrawerLabels = computed(() => {
  if (isMobile.value) return true
  if (isDesktop.value) return true
  return !drawerMini.value || drawerHover.value
})
const alertCount = computed(() => appAlerts.value.length)
const restrictedToKyb = computed(() => authStore.isAuthenticated && !authStore.isPlanActive)
const showLicenseBanner = computed(() => {
  const status = authStore.organization?.licenseStatus
  return status === 'pending' || status === 'suspended'
})
const userIdentity = computed(() => authStore.user?.email || 'Operateur')
const userDisplayName = computed(() => userIdentity.value.split('@')[0] || 'Operateur')
const currentPlant = computed<Plant | null>(() => {
  const routeId = String(route.params.id || '')

  if (plantsStore.currentPlant?.id === routeId) {
    return plantsStore.currentPlant
  }

  return plantsStore.plants.find((plant) => plant.id === routeId) ?? null
})
const roomOccupancy = computed(() => plantsStore.rooms.map((room) => ({
  room,
  count: plantsStore.plants.filter((plant) => typeof plant.room === 'string' ? plant.room.endsWith(`/${room.id}`) : plant.room.id === room.id).length,
})))
const appAlerts = computed<AlertItem[]>(() => {
  const alerts: AlertItem[] = []

  if (plantsStore.error) {
    alerts.push({
      id: 'runtime',
      title: 'Synchronisation',
      message: plantsStore.error,
      severity: 'critical',
      context: 'Verifiez le backend ou la connectivite.',
    })
  }

  roomOccupancy.value.filter(({ room, count }) => count >= room.capacityMax).slice(0, 4).forEach(({ room, count }) => {
    alerts.push({
      id: `room-${room.id}-capacity`,
      title: room.name,
      message: `${count}/${room.capacityMax} plants actifs`,
      severity: 'warning',
      context: 'Capacite maximale atteinte',
    })
  })

  return alerts
})
const roomOptions = computed(() => plantsStore.roomIriList())
const strainOptions = computed(() => plantsStore.strainIriList())
const stageOptions = computed(() => {
  if (!currentPlant.value) return []
  if (currentPlant.value.stage === 'germination') return [{ label: 'Passer en vegetation', transition: 'vegetation' as const }]
  if (currentPlant.value.stage === 'vegetation') return [{ label: 'Passer en floraison', transition: 'flowering' as const }]
  if (currentPlant.value.stage === 'flowering') return [{ label: 'Marquer recolte', transition: 'harvest' as const }]

  return []
})

const mobileNavigation = [
  { label: 'Accueil', icon: 'mdi-home-outline', to: '/dashboard/overview' },
  { label: 'Plants', icon: 'mdi-sprout-outline', to: '/plants' },
  { label: 'Salles', icon: 'mdi-door-open', to: '/rooms' },
  { label: 'Capteurs', icon: 'mdi-thermometer-lines', to: '/sensors' },
  { label: 'Plus', icon: 'mdi-dots-horizontal', to: '/more' },
] as const

const desktopNavigation = [
  ...mobileNavigation.slice(0, 4),
  { label: 'Conformite', icon: 'mdi-shield-check-outline', to: '/compliance' },
  { label: 'Facturation', icon: 'mdi-credit-card-outline', to: '/billing' },
  { label: 'Parametres', icon: 'mdi-cog-outline', to: '/settings' },
  mobileNavigation[4],
] as const

const headerTitle = computed(() => {
  if (route.name === 'plants-list') return 'Plants'
  if (route.name === 'plant-detail') return 'Plant detail'
  if (route.name === 'rooms-dashboard') return 'Rooms'
  if (route.name === 'sensors-dashboard') return 'Sensors'
  if (route.name === 'kyb') return 'KYB'
  if (route.name === 'compliance') return 'Compliance'
  if (route.name === 'billing' || route.name === 'billing-success') return 'Billing'
  if (route.name === 'settings') return 'Settings'
  if (route.name === 'more') return 'More'

  return 'Dashboard'
})

const breadcrumb = computed(() => {
  if (route.name === 'plant-detail') return 'Plants / Detail'
  if (route.name === 'rooms-dashboard') return 'Operations / Rooms'
  if (route.name === 'sensors-dashboard') return 'Operations / Sensors'
  if (route.name === 'kyb') return 'Compliance / KYB'
  if (route.name === 'compliance') return 'Compliance / CTS'
  if (route.name === 'billing' || route.name === 'billing-success') return 'Settings / Billing'
  if (route.name === 'settings') return 'Settings / Account'

  return 'Operations / Overview'
})

const mobileKicker = computed(() => {
  if (route.name === 'plants-list') return 'Terrain'
  if (route.name === 'plant-detail') return 'Suivi plant'
  if (route.name === 'rooms-dashboard') return 'Monitoring'
  if (route.name === 'sensors-dashboard') return 'Capteurs'
  if (route.name === 'kyb') return 'Conformite'
  if (route.name === 'compliance') return 'CTS'
  if (route.name === 'billing' || route.name === 'billing-success') return 'Abonnement'
  if (route.name === 'settings') return 'Compte'

  return 'Cultivation'
})

const fabIcon = computed(() => {
  if (route.name === 'plant-detail') return 'mdi-swap-horizontal'
  if (route.name === 'plants-list') return 'mdi-plus'

  return 'mdi-plus'
})

watch(() => plantsStore.rooms, (items) => {
  if (!createPlantForm.room && items.length) {
    createPlantForm.room = plantsStore.roomIriList()[0]?.value ?? ''
  }
}, { immediate: true })

watch(() => plantsStore.strains, (items) => {
  if (!createPlantForm.strain && items.length) {
    createPlantForm.strain = plantsStore.strainIriList()[0]?.value ?? ''
  }
}, { immediate: true })

watch([isMobile, isTablet, isPortrait], ([mobile]) => {
  drawerOpen.value = !mobile
}, { immediate: true })

watch(() => route.params.id, async (id) => {
  if (route.name === 'plant-detail' && typeof id === 'string' && id) {
    try {
      await Promise.all([plantsStore.fetchPlant(id), plantsStore.fetchEvents(id)])
    } catch (error) {
      console.error('Plant route sync failed', error)
    }
  }
})

function openStageDialog() {
  if (route.name === 'plant-detail') {
    stageDialogOpen.value = true
  }
}

onMounted(async () => {
  try {
    await authStore.fetchMe()
    await plantsStore.ensureSupportData()
    if (route.name === 'plant-detail' && typeof route.params.id === 'string') {
      await Promise.all([plantsStore.fetchPlant(route.params.id), plantsStore.fetchEvents(route.params.id)])
    }
  } catch (error) {
    console.error('App layout bootstrap failed', error)
  }

  window.addEventListener('plant-stage:open', openStageDialog)
})

onUnmounted(() => {
  window.removeEventListener('plant-stage:open', openStageDialog)
})

function handleFab() {
  if (route.name === 'plant-detail') {
    stageDialogOpen.value = true
    return
  }

  createPlantOpen.value = true
}

function openNotifications() {
  notificationsOpen.value = true
}

function handleNavigationClick() {
  if (isMobile.value) {
    drawerOpen.value = false
  }
}

function openConfirmDialog(title: string, message: string, action: () => Promise<void>) {
  confirmDialog.open = true
  confirmDialog.title = title
  confirmDialog.message = message
  confirmedAction.value = action
}

function closeConfirmDialog() {
  confirmDialog.open = false
  confirmDialog.title = ''
  confirmDialog.message = ''
  confirmedAction.value = null
}

async function logout() {
  authStore.logout()
  await router.push({ name: 'auth' })
}

async function submitCreatePlant() {
  actionPending.value = true

  try {
    const plant = await plantsStore.createPlant({
      room: createPlantForm.room,
      strain: createPlantForm.strain || null,
      germinatedAt: new Date(createPlantForm.germinatedAt).toISOString(),
      rfidTag: createPlantForm.rfidTag,
    })
    createPlantOpen.value = false
    createPlantForm.rfidTag = ''
    $q.notify({ type: 'positive', message: 'Plant cree.', position: isMobile.value ? 'bottom' : 'top-right', timeout: 2000 })
    await router.push({ name: 'plant-detail', params: { id: plant.id } })
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Creation failed.', position: isMobile.value ? 'bottom' : 'top-right', timeout: 4000 })
  } finally {
    actionPending.value = false
  }
}

async function submitStageChange(transition: PlantStage) {
  if (!currentPlant.value) {
    return
  }

  const executeTransition = async () => {
    actionPending.value = true

    try {
      await plantsStore.changeStage(currentPlant.value.id, transition)
      closeConfirmDialog()
      stageDialogOpen.value = false
      $q.notify({ type: 'positive', message: 'Stage updated.', position: isMobile.value ? 'bottom' : 'top-right', timeout: 2000 })
    } catch (error) {
      $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Transition failed.', position: isMobile.value ? 'bottom' : 'top-right', timeout: 4000 })
    } finally {
      actionPending.value = false
    }
  }

  if (transition === 'harvest') {
    openConfirmDialog(
      'Confirmer la recolte',
      `Le plant ${plantsStore.plantName(currentPlant.value)} va passer en recolte.`,
      executeTransition,
    )
    return
  }

  await executeTransition()
}

async function runConfirmedAction() {
  if (!confirmedAction.value) {
    return
  }

  await confirmedAction.value()
}
</script>

<style scoped lang="scss">
@use '../css/breakpoints.sass' as bp;

.app-shell {
  background: #f7f8fa;
  color: #1a202c;
  min-height: 100vh;
}

.offline-banner {
  position: sticky;
  top: 0;
  z-index: 2100;
  padding: calc(10px + env(safe-area-inset-top)) 16px 10px;
  background: #fff7d6;
  color: #8a5a08;
  font-size: 0.875rem;
  font-weight: 600;
}

.license-banner {
  border-bottom: 1px solid #f6e05e;
  background: #fff8db;
  color: #744210;
}

.app-header {
  background: rgba(247, 248, 250, 0.96);
  color: #1a202c;
  border-bottom: 1px solid #e2e8f0;
  backdrop-filter: blur(12px);
}

.app-header__toolbar {
  min-height: 56px;
  padding: env(safe-area-inset-top) 12px 0;
  gap: 10px;
}

.app-header__brand {
  display: flex;
  align-items: center;
  gap: 12px;
  min-width: 0;
  flex: 1 1 auto;
}

.app-header__copy {
  min-width: 0;
}

.app-header__brand--centered {
  justify-content: center;
  text-align: center;
}

.app-header__brand strong,
.app-header__brand span {
  display: block;
}

.app-header__brand strong {
  font-size: 1rem;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.app-header__brand span {
  color: #718096;
  font-size: 0.875rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.app-header__kicker {
  font-size: 0.72rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.app-header__logo {
  width: 40px;
  height: 40px;
  border-radius: 12px;
}

.app-header__search {
  width: min(420px, 100%);
}

.app-header__notify {
  min-width: 48px;
  min-height: 48px;
  flex: 0 0 auto;
}

.app-header__action {
  min-width: 48px;
  min-height: 48px;
  flex: 0 0 auto;
  color: #1b6b3a;
}

.app-header__menu {
  min-width: 48px;
  min-height: 48px;
  flex: 0 0 auto;
}

.app-drawer {
  background: #fff;
}

.app-drawer__content {
  display: flex;
  flex-direction: column;
  min-height: 100%;
  padding: calc(16px + env(safe-area-inset-top)) 12px calc(16px + env(safe-area-inset-bottom));
}

.app-nav {
  display: grid;
  gap: 8px;
  flex: 1 1 auto;
}

.app-nav__item {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 48px;
  padding: 0 14px;
  border-radius: 12px;
  color: #4a5568;
  text-decoration: none;
}

.app-nav__item--active,
.app-nav__item:hover {
  background: #e8f5ee;
  color: #1b6b3a;
}

.app-page-container {
  overflow-x: hidden;
  overflow-y: auto;
}

.app-page {
  background: #f7f8fa;
  min-height: auto;
  height: auto;
  overflow: visible;
}

.app-page__inner {
  width: 100%;
  margin: 0 auto;
  padding: 12px 0 calc(128px + env(safe-area-inset-bottom));
}

.app-footer {
  background: rgba(255, 255, 255, 0.96);
  border-top: 1px solid #e2e8f0;
  backdrop-filter: blur(12px);
  padding-bottom: env(safe-area-inset-bottom);
}

.bottom-tabs {
  min-height: 56px;
  color: #718096;
}

.bottom-tabs :deep(.q-tab) {
  min-height: 56px;
  color: #718096;
}

.bottom-tabs :deep(.q-tab__icon),
.bottom-tabs :deep(.q-tab__label) {
  color: inherit;
}

.bottom-tabs :deep(.q-tab--active) {
  color: #1b6b3a;
}

.bottom-tabs :deep(.q-tab--inactive) {
  color: #718096;
}

.notifications-panel,
.action-dialog {
  width: 100vw;
  max-width: none;
  min-height: calc(100vh - 56px - env(safe-area-inset-top));
  border-radius: 0;
  padding: 16px;
}

.notifications-panel__header,
.action-dialog__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 12px;
}

.notifications-panel__eyebrow {
  margin: 0 0 6px;
  color: #718096;
  font-size: 0.75rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.notifications-panel__header h2,
.action-dialog__header h2 {
  margin: 0;
  font-size: 1.25rem;
  font-weight: 600;
}

.notifications-panel__list,
.action-dialog__body {
  display: grid;
  gap: 12px;
}

.action-dialog__body--chips {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.notifications-panel__item {
  display: grid;
  gap: 8px;
  padding: 12px;
  border-radius: 14px;
  background: #f8fafc;
  border: 1px solid #e2e8f0;
}

.notifications-panel__item-top {
  display: flex;
  align-items: center;
  gap: 10px;
}

.notifications-panel__severity {
  padding: 4px 8px;
  border-radius: 999px;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
}

.notifications-panel__severity--warning {
  background: #fff5e7;
  color: #b7791f;
}

.notifications-panel__severity--critical {
  background: #fff0f0;
  color: #c53030;
}

.notifications-panel__empty {
  display: grid;
  justify-items: start;
  gap: 10px;
  padding: 12px 0 4px;
}

.action-dialog__footer {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-top: 16px;
}

.app-drawer__footer {
  display: grid;
  gap: 12px;
  padding-top: 16px;
  border-top: 1px solid #e2e8f0;
}

.app-drawer__user {
  display: grid;
  gap: 4px;
}

.app-drawer__user strong,
.app-drawer__user span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-drawer__user span {
  color: #718096;
  font-size: 0.875rem;
}

.action-dialog__hint {
  margin: 0;
  color: #4a5568;
  line-height: 1.55;
}

.action-dialog__chip {
  min-height: 48px;
  align-items: center;
  justify-content: center;
  font-weight: 600;
}

.action-dialog--confirm {
  max-width: 460px;
}

.action-dialog :deep(.q-field__control) {
  background: #fff;
}

.action-dialog :deep(.q-field__native),
.action-dialog :deep(.q-field__input) {
  font-size: 0.95rem;
}

.action-dialog :deep(.q-field__label) {
  color: #718096;
}

@include bp.mobile-landscape {
  .app-header__toolbar {
    min-height: 48px;
  }

  .app-page__inner {
    padding-bottom: 16px;
  }

  .notifications-panel,
  .action-dialog {
    min-height: calc(100vh - 48px - env(safe-area-inset-top));
  }
}

@include bp.tablet {
  .app-header__toolbar {
    min-height: 64px;
    padding: 0 16px;
  }

  .app-page__inner {
    width: min(100%, 1280px);
    padding-top: 16px;
    padding-bottom: 32px;
  }

  .notifications-panel,
  .action-dialog {
    width: min(600px, calc(100vw - 48px));
    max-width: 600px;
    min-height: auto;
    border-radius: 20px;
    padding: 18px;
  }

  .action-dialog__body--chips {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .action-dialog__footer {
    display: flex;
    justify-content: flex-end;
  }
}

@include bp.desktop {
  .app-header__toolbar {
    min-height: 64px;
  }

  .app-page__inner {
    width: min(100%, 1280px);
  }
}
</style>
