import { createRouter, createWebHistory } from 'vue-router'
import { pinia } from '../plugins/pinia'
import { useUserStore } from '../stores/useUserStore'
import AuthView from '../views/AuthView.vue'
import DashboardView from '../views/DashboardView.vue'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/',
      redirect: '/dashboard',
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
      path: '/dashboard',
      name: 'dashboard',
      component: DashboardView,
      meta: {
        requiresAuth: true,
      },
    },
    {
      path: '/:pathMatch(.*)*',
      redirect: '/dashboard',
    },
  ],
})

router.beforeEach((to) => {
  const userStore = useUserStore(pinia)

  if (to.meta.requiresAuth && !userStore.isAuthenticated) {
    return {
      name: 'auth',
      query: { redirect: to.fullPath },
    }
  }

  if (to.name === 'auth' && userStore.isAuthenticated) {
    return typeof to.query.redirect === 'string' ? to.query.redirect : '/dashboard'
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
