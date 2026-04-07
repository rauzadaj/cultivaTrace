import { beforeEach, describe, expect, it } from 'vitest'
import router from './index'
import { pinia } from '@/plugins/pinia'
import { useAuthStore } from '@/stores/auth'
import type { User, UserRole } from '@/types/api'

function buildUser(
  licenseStatus: 'active' | 'pending' = 'active',
  roles: UserRole[] = ['ROLE_ORG_ADMIN'],
): User {
  return {
    id: 'user-1',
    email: 'demo@cultivatrace.local',
    roles,
    mfaEnabled: false,
    organization: {
      id: 'org-1',
      name: 'CultivaTrace Demo',
      plan: 'pro',
      licenseStatus,
    },
  }
}

describe('router guards', () => {
  const authStore = useAuthStore(pinia)

  beforeEach(async () => {
    authStore.logout()
    authStore.user = null
    window.history.replaceState({}, '', '/auth')
    await router.push('/auth')
  })

  it('redirects unauthenticated users to auth and preserves the target path', async () => {
    await router.push('/dashboard/overview')

    expect(router.currentRoute.value.name).toBe('auth')
    expect(router.currentRoute.value.query.redirect).toBe('/dashboard/overview')
  })

  it('redirects authenticated users with inactive plan to kyb', async () => {
    authStore.token = 'jwt-token'
    authStore.user = buildUser('pending')

    await router.push('/plants')

    expect(router.currentRoute.value.name).toBe('kyb')
  })

  it('redirects authenticated active users away from auth to their requested path', async () => {
    authStore.token = 'jwt-token'
    authStore.user = buildUser('active')

    await router.push('/auth?redirect=/plants')

    expect(router.currentRoute.value.fullPath).toBe('/plants')
  })

  it('keeps onboarding public for unauthenticated users', async () => {
    await router.push('/onboarding')

    expect(router.currentRoute.value.name).toBe('onboarding')
  })

  it('keeps invitation activation public for unauthenticated users', async () => {
    await router.push('/invite/accept?token=test-token')

    expect(router.currentRoute.value.name).toBe('invite-accept')
  })

  it('redirects unauthorized users away from admin routes', async () => {
    authStore.token = 'jwt-token'
    authStore.user = buildUser('active', ['ROLE_ORG_USER'])

    await router.push('/billing')

    expect(router.currentRoute.value.name).toBe('dashboard-overview')
  })
})
