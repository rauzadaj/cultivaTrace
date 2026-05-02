import { createRouter, createWebHistory } from 'vue-router'
import { pinia } from '../plugins/pinia'
import AppLayout from '../layouts/AppLayout.vue'
import { useAuthStore } from '../stores/auth'
import AuthView from '../views/AuthView.vue'
import InviteAcceptView from '../views/auth/InviteAcceptView.vue'
import OnboardingView from '../views/auth/OnboardingView.vue'
import DashboardView from '../views/DashboardView.vue'
import MoreView from '../views/MoreView.vue'
import SensorsView from '../views/SensorsView.vue'
import ComplianceView from '../views/compliance/ComplianceView.vue'
import KybView from '../views/auth/KybView.vue'
import BillingView from '../views/billing/BillingView.vue'
import ReportingView from '../views/reporting/ReportingView.vue'
import SettingsView from '../views/settings/SettingsView.vue'
import PlantDetailView from '../views/plants/PlantDetailView.vue'
import PlantListView from '../views/plants/PlantListView.vue'
import RoomDashboard from '../views/rooms/RoomDashboard.vue'
import type { UserRole } from '@/types/api'
import { canAccessRoles } from './access'

const ORG_USER_ROLES: readonly UserRole[] = ['ROLE_SUPER_ADMIN', 'ROLE_ORG_ADMIN', 'ROLE_ORG_USER']
const ORG_ADMIN_ROLES: readonly UserRole[] = ['ROLE_SUPER_ADMIN', 'ROLE_ORG_ADMIN']

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
      path: '/onboarding',
      name: 'onboarding',
      component: OnboardingView,
      meta: {
        public: true,
      },
    },
    {
      path: '/invite/accept',
      name: 'invite-accept',
      component: InviteAcceptView,
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
          meta: { roles: ORG_USER_ROLES },
        },
        {
          path: 'plants',
          name: 'plants-list',
          component: PlantListView,
          meta: { roles: ORG_USER_ROLES },
        },
        {
          path: 'plants/:id',
          name: 'plant-detail',
          component: PlantDetailView,
          meta: { roles: ORG_USER_ROLES },
        },
        {
          path: 'rooms',
          name: 'rooms-dashboard',
          component: RoomDashboard,
          meta: { roles: ORG_USER_ROLES },
        },
        {
          path: 'sensors',
          name: 'sensors-dashboard',
          component: SensorsView,
          meta: { roles: ORG_USER_ROLES },
        },
        {
          path: 'more',
          name: 'more',
          component: MoreView,
          meta: { roles: ORG_USER_ROLES },
        },
        {
          path: 'kyb',
          name: 'kyb',
          component: KybView,
          meta: { roles: ORG_USER_ROLES },
        },
        {
          path: 'billing',
          name: 'billing',
          component: BillingView,
          meta: { roles: ORG_ADMIN_ROLES },
        },
        {
          path: 'compliance',
          name: 'compliance',
          component: ComplianceView,
          meta: { roles: ORG_ADMIN_ROLES },
        },
        {
          path: 'settings',
          name: 'settings',
          component: SettingsView,
          meta: { roles: ORG_ADMIN_ROLES },
        },
        {
          path: 'reporting',
          name: 'reporting',
          component: ReportingView,
          meta: { roles: ORG_ADMIN_ROLES },
        },
        {
          path: 'billing/success',
          name: 'billing-success',
          component: () => import('../views/billing/BillingSuccessView.vue'),
          meta: { roles: ORG_ADMIN_ROLES },
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

  const requiredRoles = to.matched.flatMap((record) => {
    const roles = record.meta.roles

    return Array.isArray(roles) ? roles : []
  }) as UserRole[]

  if (requiredRoles.length > 0 && authStore.user && !canAccessRoles(authStore.user, requiredRoles)) {
    return { name: 'dashboard-overview' }
  }

  if ((to.name === 'auth' || to.name === 'onboarding' || to.name === 'invite-accept') && authStore.isAuthenticated) {
    const redirect = to.query.redirect
    const safeRedirect =
      typeof redirect === 'string' && /^\/(?!\/)/.test(redirect)
        ? redirect
        : '/dashboard/overview'

    return authStore.isPlanActive ? safeRedirect : '/kyb'
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
