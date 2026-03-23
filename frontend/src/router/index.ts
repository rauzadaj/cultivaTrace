import { createRouter, createWebHistory } from 'vue-router'
import { pinia } from '../plugins/pinia'
import AppLayout from '../layouts/AppLayout.vue'
import { useAuthStore } from '../stores/auth'
import AuthView from '../views/AuthView.vue'
import DashboardView from '../views/DashboardView.vue'
import MoreView from '../views/MoreView.vue'
import SensorsView from '../views/SensorsView.vue'
import ComplianceView from '../views/compliance/ComplianceView.vue'
import KybView from '../views/auth/KybView.vue'
import BillingView from '../views/billing/BillingView.vue'
import SettingsView from '../views/settings/SettingsView.vue'
import PlantDetailView from '../views/plants/PlantDetailView.vue'
import PlantListView from '../views/plants/PlantListView.vue'
import RoomDashboard from '../views/rooms/RoomDashboard.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/dashboard/overview',
    },
    {
      path: '/auth',
      name: 'auth',
      component: AuthView,
      meta: {
        public: true,
      },
    },
    {
      path: '/',
      component: AppLayout,
      meta: {
        requiresAuth: true,
      },
      children: [
        {
          path: 'dashboard/overview',
          name: 'dashboard-overview',
          component: DashboardView,
        },
        {
          path: 'plants',
          name: 'plants-list',
          component: PlantListView,
        },
        {
          path: 'plants/:id',
          name: 'plant-detail',
          component: PlantDetailView,
        },
        {
          path: 'rooms',
          name: 'rooms-dashboard',
          component: RoomDashboard,
        },
        {
          path: 'sensors',
          name: 'sensors-dashboard',
          component: SensorsView,
        },
        {
          path: 'more',
          name: 'more',
          component: MoreView,
        },
        {
          path: 'kyb',
          name: 'kyb',
          component: KybView,
        },
        {
          path: 'billing',
          name: 'billing',
          component: BillingView,
        },
        {
          path: 'compliance',
          name: 'compliance',
          component: ComplianceView,
        },
        {
          path: 'settings',
          name: 'settings',
          component: SettingsView,
        },
        {
          path: 'billing/success',
          name: 'billing-success',
          component: () => import('../views/billing/BillingSuccessView.vue'),
        },
        {
          path: 'dashboard/lots',
          redirect: '/plants',
        },
        {
          path: 'dashboard/services',
          redirect: '/rooms',
        },
        {
          path: 'dashboard/analytics',
          redirect: '/sensors',
        },
        {
          path: 'dashboard/catalog',
          redirect: '/more',
        },
      ],
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/dashboard/overview',
    },
  ],
})

router.beforeEach((to) => {
  const authStore = useAuthStore(pinia)

  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return {
      name: 'auth',
      query: { redirect: to.fullPath },
    }
  }

  if (authStore.isAuthenticated && !authStore.isPlanActive && to.name !== 'kyb') {
    return { name: 'kyb' }
  }

  if (to.name === 'auth' && authStore.isAuthenticated) {
    return authStore.isPlanActive
      ? (typeof to.query.redirect === 'string' ? to.query.redirect : '/dashboard/overview')
      : '/kyb'
  }

  return true
})

export function redirectToAuth() {
  const currentPath = router.currentRoute.value.fullPath

  if (router.currentRoute.value.name === 'auth') {
    return
  }

  void router.push({
    name: 'auth',
    query: currentPath && currentPath !== '/auth' ? { redirect: currentPath } : undefined,
  })
}

export default router
