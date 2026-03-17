<template>
  <q-layout view="hHh Lpr lFf" class="app-shell">
    <div v-if="!offlineState.isOnline.value" class="offline-banner">
      Hors ligne — {{ offlineState.pendingCount.value }} actions en attente de synchronisation
    </div>

    <q-header class="app-header">
      <q-toolbar class="app-header__toolbar">
        <div class="app-header__brand">
          <img src="/cultivatrace.png" alt="CultivaTrace" class="app-header__logo">
          <div>
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

        <q-btn flat round class="app-header__notify" @click="openNotifications">
          <q-icon name="mdi-bell-outline" size="22px" />
          <q-badge v-if="alertCount > 0" color="negative" floating rounded />
        </q-btn>

        <q-btn v-if="!isMobile" flat round icon="mdi-account-circle-outline">
          <q-menu anchor="bottom right" self="top right">
            <q-list style="min-width: 180px">
              <q-item>
                <q-item-section>{{ userStore.userEmail || userStore.operatorLabel }}</q-item-section>
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
      v-if="!isMobile"
      v-model="drawerOpen"
      :mini="isTablet && !drawerHover"
      :width="240"
      :mini-width="60"
      bordered
      class="app-drawer"
      show-if-above
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
          >
            <q-icon :name="item.icon" size="22px" />
            <span v-if="!isTablet || drawerHover">{{ item.label }}</span>
          </RouterLink>
        </nav>
      </div>
    </q-drawer>

    <q-page-container>
      <q-page class="app-page">
        <div class="app-page__inner">
          <RouterView />
        </div>
      </q-page>
    </q-page-container>

    <q-footer v-if="isMobile" class="app-footer">
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

    <q-page-sticky position="bottom-right" :offset="[16, isMobile ? 88 : 24]">
      <c-btn variant="primary" round class="context-fab" @click="handleFab">
        <q-icon :name="fabIcon" size="24px" />
      </c-btn>
    </q-page-sticky>
  </q-layout>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router'
import { useQuasar } from 'quasar'
import CBtn from '../components/ui/CBtn.vue'
import CInput from '../components/ui/CInput.vue'
import { useDisplay } from '../composables/useDisplay'
import { useOfflineQueue } from '../composables/useOfflineQueue'
import { useCropStore } from '../stores/useCropStore'
import { useUserStore } from '../stores/useUserStore'

const route = useRoute()
const router = useRouter()
const $q = useQuasar()
const cropStore = useCropStore()
const userStore = useUserStore()
const offlineState = useOfflineQueue()
const { xs, tablet, desktop } = useDisplay()

const drawerOpen = ref(true)
const drawerHover = ref(false)

const isMobile = computed(() => xs.value)
const isTablet = computed(() => tablet.value)
const isDesktop = computed(() => desktop.value)
const alertCount = computed(() => cropStore.error ? 1 : cropStore.journalEntries.filter((entry) => entry.type === 'environment_check').length)

const mobileNavigation = [
  { label: 'Accueil', icon: 'mdi-home-outline', to: '/dashboard/overview' },
  { label: 'Plants', icon: 'mdi-sprout-outline', to: '/plants' },
  { label: 'Salles', icon: 'mdi-door-open', to: '/rooms' },
  { label: 'Capteurs', icon: 'mdi-thermometer-lines', to: '/sensors' },
  { label: 'Plus', icon: 'mdi-dots-horizontal', to: '/more' },
] as const

const desktopNavigation = mobileNavigation

const headerTitle = computed(() => {
  if (route.name === 'plants-list') return 'Plants'
  if (route.name === 'plant-detail') return 'Plant detail'
  if (route.name === 'rooms-dashboard') return 'Rooms'
  if (route.name === 'sensors-dashboard') return 'Sensors'
  if (route.name === 'more') return 'More'

  return 'Dashboard'
})

const breadcrumb = computed(() => {
  if (route.name === 'plant-detail') return 'Plants / Detail'
  if (route.name === 'rooms-dashboard') return 'Operations / Rooms'
  if (route.name === 'sensors-dashboard') return 'Operations / Sensors'

  return 'Operations / Overview'
})

const fabIcon = computed(() => {
  if (route.name === 'plant-detail') return 'mdi-swap-horizontal'
  if (route.name === 'plants-list') return 'mdi-plus'

  return 'mdi-plus'
})

onMounted(async () => {
  await cropStore.loadDashboard()
  cropStore.startRealtimePolling()
})

onUnmounted(() => {
  cropStore.stopRealtimePolling()
})

function handleFab() {
  if (route.name === 'plant-detail') {
    $q.notify({
      type: 'positive',
      message: 'Quick stage change ready.',
      position: isMobile.value ? 'bottom' : 'top-right',
      timeout: 2000,
    })
    return
  }

  $q.notify({
    type: 'positive',
    message: 'Create flow ready.',
    position: isMobile.value ? 'bottom' : 'top-right',
    timeout: 2000,
  })
}

function openNotifications() {
  $q.notify({
    type: alertCount.value > 0 ? 'warning' : 'positive',
    message: alertCount.value > 0 ? `${alertCount.value} active alerts` : 'No active alerts',
    position: isMobile.value ? 'bottom' : 'top-right',
    timeout: 2000,
  })
}

async function logout() {
  cropStore.stopRealtimePolling()
  userStore.clearSession()
  await router.push({ name: 'auth' })
}
</script>

<style scoped>
.app-shell {
  background: #f7f8fa;
  color: #1a202c;
}

.offline-banner {
  position: sticky;
  top: 0;
  z-index: 2100;
  padding: 10px 16px;
  background: #fff7d6;
  color: #8a5a08;
  font-size: 0.875rem;
  font-weight: 600;
}

.app-header {
  background: rgba(247, 248, 250, 0.96);
  color: #1a202c;
  border-bottom: 1px solid #e2e8f0;
  backdrop-filter: blur(12px);
}

.app-header__toolbar {
  min-height: 72px;
  padding: 0 16px;
  gap: 12px;
}

.app-header__brand {
  display: flex;
  align-items: center;
  gap: 12px;
}

.app-header__brand strong,
.app-header__brand span {
  display: block;
}

.app-header__brand span {
  color: #718096;
  font-size: 0.875rem;
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
}

.app-drawer {
  background: #fff;
}

.app-drawer__content {
  padding: 16px 12px;
}

.app-nav {
  display: grid;
  gap: 8px;
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

.app-page {
  background: #f7f8fa;
}

.app-page__inner {
  width: min(1280px, calc(100vw - 32px));
  margin: 0 auto;
  padding: 16px 0 104px;
}

.app-footer {
  background: rgba(255, 255, 255, 0.96);
  border-top: 1px solid #e2e8f0;
  backdrop-filter: blur(12px);
}

.bottom-tabs {
  min-height: 72px;
}

.context-fab {
  min-width: 56px;
  min-height: 56px;
  border-radius: 999px;
}

@media (max-width: 767px) {
  .app-page__inner {
    width: calc(100vw - 24px);
    padding-bottom: 124px;
  }

  .app-header__toolbar {
    min-height: 64px;
  }
}
</style>
