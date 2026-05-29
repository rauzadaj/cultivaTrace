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
      Offline — {{ offlineState.pendingCount }} actions pending sync
    </div>

    <q-banner v-if="showLicenseBanner" inline-actions class="license-banner">
      <div>
        License {{ authStore.organization?.licenseStatus === 'pending' ? 'pending verification' : 'suspended' }}.
        Complete your KYB file to maintain full access.
      </div>
      <template #action>
        <q-btn flat color="primary" label="Verify my license" to="/kyb" />
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
          <c-input class="app-header__search" dense standout="bg-white text-dark" placeholder="Global search">
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
      :mini-width="64"
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
            v-for="item in visibleDesktopNavigation"
            :key="item.to"
            :to="item.to"
            class="app-nav__item"
            :class="{ 'app-nav__item--collapsed': !showDrawerLabels }"
            active-class="app-nav__item--active"
            @click="handleNavigationClick"
          >
            <q-icon :name="item.icon" size="20px" class="app-nav__icon" />
            <span v-if="showDrawerLabels" class="app-nav__label">{{ item.label }}</span>
            <q-tooltip v-if="!showDrawerLabels" anchor="center right" self="center left" :offset="[12, 0]" class="app-nav__tooltip">
              {{ item.label }}
            </q-tooltip>
          </RouterLink>
        </nav>

        <button v-if="!isMobile" class="sidebar-collapse-btn" :class="{ 'sidebar-collapse-btn--collapsed': !showDrawerLabels }" @click="toggleDesktopSidebar">
          <q-icon :name="desktopSidebarCollapsed ? 'mdi-chevron-right' : 'mdi-chevron-left'" size="16px" />
          <span v-if="showDrawerLabels">Collapse</span>
        </button>

        <div v-if="isMobile" class="app-drawer__footer">
          <div class="app-drawer__user">
            <strong>{{ userDisplayName }}</strong>
            <span>{{ authStore.user?.email || 'operator@cultivatrace.local' }}</span>
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
            <h2>Active alerts</h2>
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
              <q-btn flat dense no-caps color="primary" label="Acknowledge" @click="acknowledgeAlert(alert.id)" />
            </div>
          </article>
        </div>

        <div v-else-if="!alertsStore.loading" class="notifications-panel__empty">
          <q-icon name="mdi-check-circle-outline" size="28px" />
          <strong>No pending alerts</strong>
        </div>

        <div v-else class="notifications-panel__empty">
          <q-spinner color="primary" size="28px" />
          <strong>Loading alerts…</strong>
        </div>
      </q-card>
    </q-dialog>

    <q-dialog v-model="createPlantOpen" :position="isMobile ? 'bottom' : 'standard'" :maximized="isMobile" :full-width="isTablet">
      <q-card class="action-dialog">
        <div class="action-dialog__header">
          <div>
            <p class="notifications-panel__eyebrow">Quick action</p>
            <h2>New plant</h2>
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
            label="Room"
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
            label="Genetics"
            stack-label
          />
          <c-input v-model="createPlantForm.rfidTag" label="RFID / Tag" />
          <c-input v-model="createPlantForm.germinatedAt" label="Germination date" type="date" />
        </div>

        <div class="action-dialog__footer">
          <c-btn variant="ghost" @click="createPlantOpen = false">Cancel</c-btn>
          <c-btn variant="primary" :loading="actionPending" @click="submitCreatePlant">Create</c-btn>
        </div>
      </q-card>
    </q-dialog>

    <q-dialog v-model="stageDialogOpen" :position="isMobile ? 'bottom' : 'standard'" :maximized="isMobile" :full-width="isTablet">
      <q-card class="action-dialog">
        <div class="action-dialog__header">
          <div>
            <p class="notifications-panel__eyebrow">Quick action</p>
            <h2>Change stage</h2>
          </div>
          <q-btn flat round icon="mdi-close" @click="stageDialogOpen = false" />
        </div>

        <div class="action-dialog__body action-dialog__body--chips">
            <p v-if="!stageOptions.length" class="action-dialog__hint">No transitions available for this plant.</p>
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
          <c-btn variant="ghost" @click="stageDialogOpen = false">Close</c-btn>
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
          <c-btn variant="ghost" @click="closeConfirmDialog">Cancel</c-btn>
          <c-btn variant="danger" :loading="actionPending" @click="runConfirmedAction">Confirm</c-btn>
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
const desktopSidebarCollapsed = ref(false)
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
const drawerMini = computed(() =>
  (isTablet.value && isPortrait.value) || (isDesktop.value && desktopSidebarCollapsed.value),
)
const drawerWidth = computed(() => {
  if (isDesktop.value) return desktopSidebarCollapsed.value ? 64 : 240
  if (isTablet.value && isPortrait.value) return 64
  return 220
})
const showDrawerLabels = computed(() => {
  if (isMobile.value) return true
  if (isDesktop.value) return !desktopSidebarCollapsed.value
  return !drawerMini.value || drawerHover.value
})
const alertCount = computed(() => notifications.value.filter((alert) => !alert.acknowledgedAt).length)
const restrictedToKyb = computed(() => authStore.isAuthenticated && !authStore.isPlanActive)
const showLicenseBanner = computed(() => {
  const status = authStore.organization?.licenseStatus
  return status === 'pending' || status === 'suspended'
})
const userIdentity = computed(() => authStore.user?.email || 'Operator')
const userDisplayName = computed(() => userIdentity.value.split('@')[0] || 'Operator')
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
      context: 'Check the backend or connectivity.',
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
  if (currentPlant.value.stage === 'germination') return [{ label: 'Move to vegetation', transition: 'vegetation' as const }]
  if (currentPlant.value.stage === 'vegetation') return [{ label: 'Move to flowering', transition: 'flowering' as const }]
  if (currentPlant.value.stage === 'flowering') return [{ label: 'Mark as harvested', transition: 'harvest' as const }]

  return []
})

const ORG_USER_ROLES: readonly UserRole[] = ['ROLE_SUPER_ADMIN', 'ROLE_ORG_ADMIN', 'ROLE_ORG_USER']
const ORG_ADMIN_ROLES: readonly UserRole[] = ['ROLE_SUPER_ADMIN', 'ROLE_ORG_ADMIN']
const SUPER_ADMIN_ROLES: readonly UserRole[] = ['ROLE_SUPER_ADMIN']

const mobileNavigation: readonly RoleScopedNavigationItem[] = [
  { label: 'Home', icon: 'mdi-home-outline', to: '/dashboard/overview', roles: ORG_USER_ROLES },
  { label: 'Plants', icon: 'mdi-sprout-outline', to: '/plants', roles: ORG_USER_ROLES },
  { label: 'Rooms', icon: 'mdi-door-open', to: '/rooms', roles: ORG_USER_ROLES },
  { label: 'Sensors', icon: 'mdi-thermometer-lines', to: '/sensors', roles: ORG_USER_ROLES },
  { label: 'Plus', icon: 'mdi-dots-horizontal', to: '/more', roles: ORG_USER_ROLES },
] as const

const desktopNavigation: readonly RoleScopedNavigationItem[] = [
  ...mobileNavigation.slice(0, 4),
  { label: 'Reporting', icon: 'mdi-file-chart-outline', to: '/reporting', roles: ORG_ADMIN_ROLES },
  { label: 'Catalog', icon: 'mdi-dna', to: '/catalog/mappings', roles: SUPER_ADMIN_ROLES },
  { label: 'Compliance', icon: 'mdi-shield-check-outline', to: '/compliance', roles: ORG_ADMIN_ROLES },
  { label: 'Billing', icon: 'mdi-credit-card-outline', to: '/billing', roles: ORG_ADMIN_ROLES },
  { label: 'Settings', icon: 'mdi-cog-outline', to: '/settings', roles: ORG_ADMIN_ROLES },
  mobileNavigation[4],
] as const

const visibleMobileNavigation = computed(() => filterNavigationItems(mobileNavigation, authStore.user))
const visibleDesktopNavigation = computed(() => filterNavigationItems(desktopNavigation, authStore.user))

const headerTitle = computed(() => {
  if (route.name === 'plants-list') return 'Plants'
  if (route.name === 'plant-detail') return 'Plant detail'
  if (route.name === 'rooms-dashboard') return 'Rooms'
  if (route.name === 'sensors-dashboard') return 'Sensors'
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
  if (route.name === 'kyb') return 'Compliance / KYB'
  if (route.name === 'compliance') return 'Compliance / CTS'
  if (route.name === 'reporting') return 'Reporting / Exports'
  if (route.name === 'billing' || route.name === 'billing-success') return 'Settings / Billing'
  if (route.name === 'settings') return 'Settings / Account'

  return 'Operations / Overview'
})

const mobileKicker = computed(() => {
  if (route.name === 'plants-list') return 'Field'
  if (route.name === 'plant-detail') return 'Plant tracking'
  if (route.name === 'rooms-dashboard') return 'Monitoring'
  if (route.name === 'sensors-dashboard') return 'Sensors'
  if (route.name === 'kyb') return 'Compliance'
  if (route.name === 'compliance') return 'CTS'
  if (route.name === 'reporting') return 'Reporting'
  if (route.name === 'billing' || route.name === 'billing-success') return 'Subscription'
  if (route.name === 'settings') return 'Account'

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

function toggleDesktopSidebar() {
  desktopSidebarCollapsed.value = !desktopSidebarCollapsed.value
}

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
    $q.notify({ type: 'positive', message: 'Alert acknowledged.', position: isMobile.value ? 'bottom' : 'top-right', timeout: 1500 })
  } catch (error) {
    $q.notify({ type: 'negative', message: error instanceof Error ? error.message : 'Failed to acknowledge alert.', position: isMobile.value ? 'bottom' : 'top-right', timeout: 3000 })
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
    $q.notify({ type: 'positive', message: 'Plant created.', position: isMobile.value ? 'bottom' : 'top-right', timeout: 2000 })
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

  const plantId = currentPlant.value.id

  const executeTransition = async () => {
    actionPending.value = true

    try {
      await plantsStore.changeStage(plantId, transition)
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
      'Confirm harvest',
      `Plant ${plantsStore.plantName(currentPlant.value)} will move to harvest.`,
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

/* ── Shell ───────────────────────────────────────────── */

.app-shell {
  background: var(--ct-bg);
  color: var(--ct-text-1);
  min-height: 100vh;
}

/* ── Banners ─────────────────────────────────────────── */

.offline-banner {
  position: sticky;
  top: 0;
  z-index: 2100;
  padding: calc(10px + env(safe-area-inset-top)) 20px 10px;
  background: #FFFBEB;
  color: #78350F;
  border-bottom: 1px solid #FDE68A;
  font-size: 0.8125rem;
  font-weight: 500;
}

.license-banner {
  background: #FFFBEB;
  color: #78350F;
  border-bottom: 1px solid #FDE68A;
}

/* ── Header ──────────────────────────────────────────── */

.app-header {
  background: rgba(255, 255, 255, 0.82);
  color: var(--ct-text-1);
  border-bottom: 1px solid var(--ct-border);
  backdrop-filter: blur(24px) saturate(200%);
  -webkit-backdrop-filter: blur(24px) saturate(200%);
  box-shadow: 0 1px 0 rgba(0, 0, 0, 0.03);
}

.app-header__toolbar {
  min-height: 56px;
  padding: env(safe-area-inset-top) 16px 0;
  gap: 8px;
}

.app-header__brand {
  display: flex;
  align-items: center;
  gap: 10px;
  min-width: 0;
  flex: 1 1 auto;
}

.app-header__copy { min-width: 0; }

.app-header__brand--centered {
  justify-content: center;
  text-align: center;
}

.app-header__brand strong,
.app-header__brand span { display: block; }

.app-header__brand strong {
  font-size: 0.9375rem;
  font-weight: 600;
  line-height: 1.2;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  letter-spacing: -0.02em;
  color: var(--ct-text-1);
}

.app-header__brand span {
  color: var(--ct-text-3);
  font-size: 0.75rem;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.app-header__kicker {
  font-size: 0.6rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--ct-text-3);
  font-weight: 700;
}

.app-header__logo {
  width: 28px;
  height: 28px;
  border-radius: 7px;
}

.app-header__search {
  width: min(380px, 100%);
  :deep(.q-field__control) {
    background: #F3F4F6 !important;
    border-radius: 8px;
    border: 1px solid transparent;
    transition: border-color 0.15s;
  }
  :deep(.q-field__control:before) { border: none !important; }
  :deep(.q-field--focused .q-field__control) { border-color: #16A34A !important; background: #fff !important; }
}

.app-header__notify,
.app-header__menu {
  min-width: 36px;
  min-height: 36px;
  flex: 0 0 auto;
  color: var(--ct-text-2);
  border-radius: 8px;
}

.app-header__action {
  min-width: 36px;
  min-height: 36px;
  flex: 0 0 auto;
  color: var(--ct-accent);
  border-radius: 8px;
}

/* ── Drawer / Sidebar ────────────────────────────────── */

.app-drawer {
  background: #FFFFFF !important;
  border-right: 1px solid var(--ct-border) !important;
  transition: width 0.22s cubic-bezier(0.4, 0, 0.2, 1) !important;
}

.app-drawer__content {
  display: flex;
  flex-direction: column;
  height: 100%;
  padding: calc(10px + env(safe-area-inset-top)) 8px calc(10px + env(safe-area-inset-bottom));
  gap: 4px;
}

/* ── Navigation ──────────────────────────────────────── */

.app-nav {
  display: flex;
  flex-direction: column;
  gap: 2px;
  flex: 1 1 auto;
  overflow: hidden;
}

.app-nav__item {
  position: relative;
  display: flex;
  align-items: center;
  gap: 10px;
  min-height: 40px;
  padding: 0 12px;
  border-radius: 8px;
  color: var(--ct-text-2);
  text-decoration: none;
  font-size: 0.875rem;
  font-weight: 500;
  white-space: nowrap;
  overflow: hidden;
  transition: background 0.12s, color 0.12s;
  cursor: pointer;
}

.app-nav__icon {
  flex-shrink: 0;
  transition: color 0.12s;
}

.app-nav__label {
  overflow: hidden;
  text-overflow: ellipsis;
  opacity: 1;
  transition: opacity 0.15s;
}

/* Collapsed state — centered icon */
.app-nav__item--collapsed {
  justify-content: center;
  padding: 0;
}

.app-nav__item:hover {
  background: var(--ct-surface-2);
  color: var(--ct-text-1);
}

/* Active — indicateur gauche + fond */
.app-nav__item--active {
  background: var(--ct-accent-light);
  color: var(--ct-accent);
  font-weight: 600;
}

.app-nav__item--active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  width: 3px;
  height: 20px;
  border-radius: 0 3px 3px 0;
  background: var(--ct-accent);
}

.app-nav__item--collapsed.app-nav__item--active::before {
  display: none;
}

/* Tooltip Quasar */
:deep(.app-nav__tooltip) {
  background: #111827;
  color: #FFFFFF;
  font-size: 0.8125rem;
  font-weight: 500;
  padding: 6px 12px;
  border-radius: 8px;
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.15);
}

/* ── Sidebar collapse button ─────────────────────────── */

.sidebar-collapse-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  width: 100%;
  min-height: 36px;
  padding: 0 12px;
  border-radius: 8px;
  border: 1px solid var(--ct-border);
  background: #FFFFFF;
  color: var(--ct-text-3);
  font-family: 'Inter', sans-serif;
  font-size: 0.8125rem;
  font-weight: 500;
  cursor: pointer;
  transition: background 0.12s, color 0.12s, border-color 0.12s;
  flex-shrink: 0;
  white-space: nowrap;
  overflow: hidden;
}

.sidebar-collapse-btn:hover {
  background: var(--ct-surface-2);
  color: var(--ct-text-2);
  border-color: #D1D5DB;
}

.sidebar-collapse-btn--collapsed {
  justify-content: center;
  padding: 0;
  border: none;
}

/* ── Page ────────────────────────────────────────────── */

.app-page-container {
  overflow-x: hidden;
  overflow-y: auto;
}

.app-page {
  background: var(--ct-bg);
  min-height: auto;
  height: auto;
  overflow: visible;
}

.app-page__inner {
  width: 100%;
  margin: 0 auto;
  padding: 20px 0 calc(120px + env(safe-area-inset-bottom));
}

/* ── Mobile footer ───────────────────────────────────── */

.app-footer {
  background: rgba(255, 255, 255, 0.88);
  border-top: 1px solid var(--ct-border);
  backdrop-filter: blur(24px) saturate(200%);
  -webkit-backdrop-filter: blur(24px) saturate(200%);
  padding-bottom: env(safe-area-inset-bottom);
}

.bottom-tabs {
  min-height: 56px;
  color: var(--ct-text-3);
}

.bottom-tabs :deep(.q-tab) {
  min-height: 56px;
  color: var(--ct-text-3);
  padding: 0 8px;
}

.bottom-tabs :deep(.q-tab__icon),
.bottom-tabs :deep(.q-tab__label) { color: inherit; }

.bottom-tabs :deep(.q-tab--active) { color: var(--ct-accent); }
.bottom-tabs :deep(.q-tab--inactive) { color: var(--ct-text-3); }

/* ── Dialogs & panels ────────────────────────────────── */

.notifications-panel,
.action-dialog {
  width: 100vw;
  max-width: none;
  min-height: calc(100vh - 56px - env(safe-area-inset-top));
  border-radius: 0;
  padding: 24px 20px;
  background: #FFFFFF !important;
  border: none !important;
  box-shadow: none !important;
}

.notifications-panel__header,
.action-dialog__header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 20px;
}

.notifications-panel__eyebrow {
  margin: 0 0 4px;
  color: var(--ct-accent);
  font-size: 0.625rem;
  text-transform: uppercase;
  letter-spacing: 0.16em;
  font-weight: 700;
}

.notifications-panel__header h2,
.action-dialog__header h2 {
  margin: 0;
  font-size: 1.375rem;
  font-weight: 700;
  letter-spacing: -0.035em;
  color: var(--ct-text-1);
}

.notifications-panel__list,
.action-dialog__body {
  display: grid;
  gap: 10px;
}

.action-dialog__body--chips {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.notifications-panel__item {
  display: grid;
  gap: 8px;
  padding: 14px 16px;
  border-radius: 12px;
  background: var(--ct-surface-2);
  border: 1px solid var(--ct-border);
}

.notifications-panel__item-top {
  display: flex;
  align-items: center;
  gap: 10px;
}

.notifications-panel__severity {
  padding: 3px 9px;
  border-radius: 999px;
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.notifications-panel__severity--warning {
  background: #FEF3C7;
  color: #92400E;
}

.notifications-panel__severity--critical {
  background: #FEE2E2;
  color: #991B1B;
}

.notifications-panel__actions {
  display: flex;
  justify-content: flex-end;
}

.notifications-panel__empty {
  display: grid;
  justify-items: start;
  gap: 8px;
  padding: 8px 0;
  color: var(--ct-text-2);
}

.action-dialog__footer {
  display: grid;
  grid-template-columns: 1fr;
  gap: 10px;
  margin-top: 20px;
}

/* ── Sidebar user footer ─────────────────────────────── */

.app-drawer__footer {
  display: grid;
  gap: 10px;
  padding-top: 14px;
  border-top: 1px solid var(--ct-border);
}

.app-drawer__user {
  display: grid;
  gap: 2px;
}

.app-drawer__user strong,
.app-drawer__user span {
  min-width: 0;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.app-drawer__user strong {
  color: var(--ct-text-1);
  font-size: 0.875rem;
  font-weight: 600;
}

.app-drawer__user span {
  color: var(--ct-text-2);
  font-size: 0.75rem;
}

/* ── Dialog body misc ────────────────────────────────── */

.action-dialog__hint {
  margin: 0;
  color: var(--ct-text-2);
  line-height: 1.65;
  font-size: 0.9rem;
}

.action-dialog__chip {
  min-height: 52px;
  align-items: center;
  justify-content: center;
  font-weight: 600;
  font-size: 0.875rem;
}

.action-dialog--confirm { max-width: 460px; }

.action-dialog :deep(.q-field__control) { background: #FFFFFF; }

.action-dialog :deep(.q-field__native),
.action-dialog :deep(.q-field__input) {
  font-size: 0.9375rem;
  color: var(--ct-text-1);
}

.action-dialog :deep(.q-field__label) { color: var(--ct-text-2); }

/* ── Breakpoints ─────────────────────────────────────── */

@include bp.mobile-landscape {
  .app-header__toolbar { min-height: 48px; }
  .app-page__inner { padding-bottom: 16px; }
  .notifications-panel,
  .action-dialog { min-height: calc(100vh - 48px - env(safe-area-inset-top)); }
}

@include bp.tablet {
  .app-header__toolbar { min-height: 60px; padding: 0 20px; }

  .app-page__inner {
    width: min(100%, 1280px);
    padding-top: 24px;
    padding-bottom: 40px;
  }

  .notifications-panel,
  .action-dialog {
    width: min(560px, calc(100vw - 48px));
    max-width: 560px;
    min-height: auto;
    border-radius: 20px;
    padding: 28px;
    border: 1px solid var(--ct-border) !important;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.10) !important;
  }

  .action-dialog__body--chips { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .action-dialog__footer { display: flex; justify-content: flex-end; }
}

@include bp.desktop {
  .app-header__toolbar { min-height: 60px; }
  .app-page__inner { width: min(100%, 1280px); }
}
</style>
