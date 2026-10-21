<template>
  <q-layout view="lHh Lpr lFf" class="app-shell" :class="{ 'ct-theme app-shell--executive': usesExecutiveTheme }">
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
    <div v-if="!offlineState.isOnline.value" class="offline-banner">
      Hors ligne — {{ offlineState.pendingCount.value }} actions en attente de synchronisation
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

    <q-header class="app-header ct-theme" :height-hint="56">
      <ShellHeader
        :mobile="isMobile"
        :title="headerTitle"
        :breadcrumb="breadcrumb"
        :organization-name="authStore.organization?.name || ''"
        :user-identity="userIdentity"
        :user-display-name="userDisplayName"
        :user-role="userRoleLabel"
        :online="offlineState.isOnline.value"
        :pending-count="offlineState.pendingCount.value"
        :alert-count="alertCount"
        :show-action="showFab"
        :action-icon="fabIcon"
        :action-label="route.name === 'plant-detail' ? 'Action rapide : changer le stade' : 'Action rapide : créer un plant'"
        @toggle-drawer="drawerOpen = !drawerOpen"
        @action="handleFab"
        @notifications="openNotifications"
        @logout="logout"
      />
    </q-header>

    <q-drawer
      v-model="drawerOpen"
      :mini="drawerMini && !drawerHover"
      :mini-to-overlay="drawerMini"
      :width="drawerWidth"
      :mini-width="60"
      :overlay="isMobile"
      :behavior="isMobile ? 'mobile' : 'desktop'"
      bordered
      class="app-drawer ct-theme"
      :show-if-above="!isMobile"
      @mouseover="drawerHover = true"
      @mouseleave="drawerHover = false"
    >
      <ShellNavigation
        :items="visibleDesktopNavigation"
        :compact="!showDrawerLabels"
        :mobile="isMobile"
        :plan="authStore.organization?.plan"
        :online="offlineState.isOnline.value"
        :pending-count="offlineState.pendingCount.value"
        :user-identity="userIdentity"
        :user-display-name="userDisplayName"
        @navigate="handleNavigationClick"
        @logout="logout"
      />
    </q-drawer>

    <q-page-container class="app-page-container">
      <q-page class="app-page">
        <div class="app-page__inner" :class="{ 'app-page__inner--journal': route.name === 'culture-journal' }">
          <RouterView />
        </div>
      </q-page>
    </q-page-container>

    <q-footer v-if="showMobileFooter" class="app-footer ct-theme">
        <q-tabs
          dense
          indicator-color="transparent"
          active-color="primary"
          class="bottom-tabs"
        >
          <q-route-tab
          v-for="item in visibleMobileNavigation"
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

        <div v-if="notifications.length" class="notifications-panel__list">
          <article v-for="alert in notifications" :key="alert.id" class="notifications-panel__item">
            <div class="notifications-panel__item-top">
              <span class="notifications-panel__severity" :class="`notifications-panel__severity--${alert.severity}`">{{ alert.severity }}</span>
              <strong>{{ alert.title }}</strong>
            </div>
            <p>{{ alert.message }}</p>
            <small v-if="alert.context">{{ alert.context }}</small>
            <div v-if="alert.acknowledgeable" class="notifications-panel__actions">
              <q-btn flat dense no-caps color="primary" label="Acquitter" @click="acknowledgeAlert(alert.id)" />
            </div>
          </article>
        </div>

        <div v-else-if="!alertsStore.loading" class="notifications-panel__empty">
          <q-icon name="mdi-check-circle-outline" size="28px" />
          <strong>Aucune alerte en attente</strong>
        </div>

        <div v-else class="notifications-panel__empty">
          <q-spinner color="primary" size="28px" />
          <strong>Chargement des alertes…</strong>
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
import { RouterView, useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import CBtn from '../components/ui/CBtn.vue'
import CInput from '../components/ui/CInput.vue'
import ShellHeader from '../components/shell/ShellHeader.vue'
import ShellNavigation from '../components/shell/ShellNavigation.vue'
import { useOfflineQueue } from '../composables/useOfflineQueue'
import { useAlertsStore } from '../stores/alerts'
import { useAuthStore } from '../stores/auth'
import { usePlantsStore } from '../stores/plants'
import type { AlertItem, Plant, PlantStage, UserRole } from '../types/api'
import { filterNavigationItems, type RoleScopedNavigationItem } from '../router/access'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const plantsStore = usePlantsStore()
const alertsStore = useAlertsStore()
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
const usesExecutiveTheme = computed(() => route.name === 'dashboard-overview' || route.name === 'culture-journal')
const showMobileFooter = computed(() => isMobile.value && isPortrait.value)
const showFab = computed(() => route.name === 'plants-list' || route.name === 'plant-detail')
const drawerMini = computed(() => isTablet.value && isPortrait.value)
const drawerWidth = computed(() => {
  if (isDesktop.value || drawerMini.value) return 256
  return 220
})
const showDrawerLabels = computed(() => {
  if (isMobile.value) return true
  if (isDesktop.value) return true
  return !drawerMini.value || drawerHover.value
})
const alertCount = computed(() => notifications.value.filter((alert) => !alert.acknowledgedAt).length)
const restrictedToKyb = computed(() => authStore.isAuthenticated && !authStore.isPlanActive)
const showLicenseBanner = computed(() => {
  const status = authStore.organization?.licenseStatus
  return status === 'pending' || status === 'suspended'
})
const userIdentity = computed(() => authStore.user?.email || 'Operateur')
const userDisplayName = computed(() => userIdentity.value.split('@')[0] || 'Operateur')
const userRoleLabel = computed(() => {
  if (authStore.isSuperAdmin) return 'Super administrateur'
  if (authStore.isOrgAdmin) return 'Administrateur'
  if (authStore.isOrgUser) return 'Opérateur'
  if (authStore.isApiUser) return 'Accès API'
  return ''
})
const currentPlant = computed<Plant | null>(() => {
  const routeId = String(route.params.id || '')

  if (plantsStore.currentPlant?.id === routeId) {
    return plantsStore.currentPlant
  }

  return plantsStore.plants.find((plant) => plant.id === routeId) ?? null
})
const notifications = computed<AlertItem[]>(() => {
  const alerts: AlertItem[] = []

  if (plantsStore.error) {
    alerts.push({
      id: 'runtime',
      title: 'Synchronisation',
      message: plantsStore.error,
      severity: 'critical',
      context: 'Verifiez le backend ou la connectivite.',
      acknowledgeable: false,
    })
  }

  alertsStore.alerts.forEach((alert) => {
    alerts.push({
      id: alert.id,
      title: alert.title,
      message: alert.message,
      severity: alert.severity,
      context: alert.context ?? undefined,
      acknowledgedAt: alert.acknowledgedAt ?? null,
      acknowledgeable: !alert.acknowledgedAt,
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

const ORG_USER_ROLES: readonly UserRole[] = ['ROLE_SUPER_ADMIN', 'ROLE_ORG_ADMIN', 'ROLE_ORG_USER']
const ORG_ADMIN_ROLES: readonly UserRole[] = ['ROLE_SUPER_ADMIN', 'ROLE_ORG_ADMIN']

const mobileNavigation: readonly RoleScopedNavigationItem[] = [
  { label: 'Accueil', icon: 'mdi-home-outline', to: '/dashboard/overview', roles: ORG_USER_ROLES },
  { label: 'Plants', icon: 'mdi-sprout-outline', to: '/plants', roles: ORG_USER_ROLES },
  { label: 'Salles', icon: 'mdi-door-open', to: '/rooms', roles: ORG_USER_ROLES },
  { label: 'Capteurs', icon: 'mdi-thermometer-lines', to: '/sensors', roles: ORG_USER_ROLES },
  { label: 'Plus', icon: 'mdi-dots-horizontal', to: '/more', roles: ORG_USER_ROLES },
] as const

const desktopNavigation: readonly RoleScopedNavigationItem[] = [
  ...mobileNavigation.slice(0, 4),
  { label: 'Journal de Culture', icon: 'mdi-notebook-check-outline', to: '/journal', roles: ORG_USER_ROLES },
  { label: 'Reporting', icon: 'mdi-file-chart-outline', to: '/reporting', roles: ORG_ADMIN_ROLES },
  { label: 'Conformite', icon: 'mdi-shield-check-outline', to: '/compliance', roles: ORG_ADMIN_ROLES },
  { label: 'Facturation', icon: 'mdi-credit-card-outline', to: '/billing', roles: ORG_ADMIN_ROLES },
  { label: 'Parametres', icon: 'mdi-cog-outline', to: '/settings', roles: ORG_ADMIN_ROLES },
  mobileNavigation[4],
] as const

const visibleMobileNavigation = computed(() => filterNavigationItems(mobileNavigation, authStore.user))
const visibleDesktopNavigation = computed(() => filterNavigationItems(desktopNavigation, authStore.user))

const headerTitle = computed(() => {
  if (route.name === 'plants-list') return 'Plants'
  if (route.name === 'plant-detail') return 'Plant detail'
  if (route.name === 'rooms-dashboard') return 'Rooms'
  if (route.name === 'sensors-dashboard') return 'Sensors'
  if (route.name === 'culture-journal') return 'Journal de Culture'
  if (route.name === 'kyb') return 'KYB'
  if (route.name === 'compliance') return 'Compliance'
  if (route.name === 'reporting') return 'Reporting'
  if (route.name === 'billing' || route.name === 'billing-success') return 'Billing'
  if (route.name === 'settings') return 'Settings'
  if (route.name === 'more') return 'More'

  return 'Dashboard'
})

const breadcrumb = computed(() => {
  if (route.name === 'plant-detail') return 'Plants / Detail'
  if (route.name === 'rooms-dashboard') return 'Operations / Rooms'
  if (route.name === 'sensors-dashboard') return 'Operations / Sensors'
  if (route.name === 'culture-journal') return 'Supervision / Journal de Culture'
  if (route.name === 'kyb') return 'Compliance / KYB'
  if (route.name === 'compliance') return 'Compliance / CTS'
  if (route.name === 'reporting') return 'Reporting / Exports'
  if (route.name === 'billing' || route.name === 'billing-success') return 'Settings / Billing'
  if (route.name === 'settings') return 'Settings / Account'

  return 'Operations / Overview'
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
    await Promise.all([plantsStore.ensureSupportData(), plantsStore.fetchPlants(), alertsStore.fetchAlerts()])
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
  void alertsStore.fetchAlerts()
}

async function acknowledgeAlert(id: string) {
  try {
    await alertsStore.acknowledgeAlert(id)
    $q.notify({ type: 'positive', message: 'Alerte acquittee.', position: isMobile.value ? 'bottom' : 'top-right', timeout: 1500 })
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Impossible d’acquitter cette alerte.', position: isMobile.value ? 'bottom' : 'top-right', timeout: 3000 })
  }
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
    await plantsStore.fetchPlants()
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
  background: color-mix(in srgb, var(--ct-bg) 90%, transparent);
  color: var(--ct-text);
  border-bottom: 1px solid var(--ct-border);
  backdrop-filter: blur(12px);
}

.app-drawer {
  background: var(--ct-nav);
  border-color: var(--ct-border);
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
  background: var(--ct-nav);
  border-top: 1px solid var(--ct-border);
  backdrop-filter: blur(12px);
  padding-bottom: env(safe-area-inset-bottom);
}

.bottom-tabs {
  min-height: 56px;
  color: var(--ct-muted);
}

.bottom-tabs :deep(.q-tab) {
  min-height: 56px;
  color: var(--ct-muted);
}

.bottom-tabs :deep(.q-tab__icon),
.bottom-tabs :deep(.q-tab__label) {
  color: inherit;
}

.bottom-tabs :deep(.q-tab--active) {
  color: var(--ct-mint) !important;
}

.bottom-tabs :deep(.q-tab--inactive) {
  color: var(--ct-muted);
}

.app-shell--executive,
.app-shell--executive .app-page {
  background: var(--ct-bg);
  color: var(--ct-text);
}

.app-shell--executive .app-page__inner {
  width: 100%;
  padding: var(--ct-space-6) var(--ct-space-6) var(--ct-space-8);
}

.app-shell--executive .app-page__inner--journal {
  padding-top: 0;
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

.notifications-panel__actions {
  display: flex;
  justify-content: flex-end;
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
  .app-page__inner {
    padding-bottom: 16px;
  }

  .notifications-panel,
  .action-dialog {
    min-height: calc(100vh - 48px - env(safe-area-inset-top));
  }
}

@include bp.tablet {
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
  .app-page__inner {
    width: min(100%, 1280px);
  }
}

@include bp.mobile {
  .app-shell--executive .app-page__inner {
    padding: var(--ct-space-4) var(--ct-space-4) calc(var(--ct-space-8) + env(safe-area-inset-bottom));
  }

  .app-shell--executive .app-page__inner--journal {
    padding: 0 var(--ct-space-4) calc(112px + env(safe-area-inset-bottom));
  }
}
</style>
