import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useAuthStore } from './auth'

describe('auth store role getters', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('derives organization role access from the new role set only', () => {
    const store = useAuthStore()

    store.user = {
      id: '1',
      email: 'admin@cultivatrace.local',
      roles: ['ROLE_ORG_ADMIN'],
      mfaEnabled: false,
      organization: {
        id: 'org-1',
        name: 'CultivaTrace',
        plan: 'pro',
        licenseStatus: 'active',
      },
    }

    expect(store.isAuthenticated).toBe(false)
    expect(store.isOrgAdmin).toBe(true)
    expect(store.isOrgUser).toBe(true)
    expect(store.isSuperAdmin).toBe(false)
    expect(store.isApiUser).toBe(false)
  })

  it('does not grant organization access from non-organization roles', () => {
    const store = useAuthStore()

    store.user = {
      id: '2',
      email: 'api@cultivatrace.local',
      roles: ['ROLE_API'],
      mfaEnabled: false,
    }

    expect(store.isOrgAdmin).toBe(false)
    expect(store.isOrgUser).toBe(false)
    expect(store.isSuperAdmin).toBe(false)
  })

  it('matches any role in the provided route guard set', () => {
    const store = useAuthStore()

    store.user = {
      id: '3',
      email: 'operator@cultivatrace.local',
      roles: ['ROLE_ORG_USER'],
      mfaEnabled: false,
    }

    expect(store.hasAnyRole(['ROLE_ORG_ADMIN'])).toBe(false)
    expect(store.hasAnyRole(['ROLE_ORG_ADMIN', 'ROLE_ORG_USER'])).toBe(true)
  })
})
