import { describe, expect, it } from 'vitest'
import { canAccessRoles, filterNavigationItems } from './access'
import type { User } from '@/types/api'

const orgUser: User = {
  id: 'user-1',
  email: 'operator@cultivatrace.local',
  roles: ['ROLE_ORG_USER'],
  mfaEnabled: false,
}

describe('route access helpers', () => {
  it('authorizes users only when one of the required roles matches', () => {
    expect(canAccessRoles(orgUser, ['ROLE_ORG_ADMIN'])).toBe(false)
    expect(canAccessRoles(orgUser, ['ROLE_ORG_ADMIN', 'ROLE_ORG_USER'])).toBe(true)
    expect(canAccessRoles(null, ['ROLE_ORG_USER'])).toBe(false)
    expect(canAccessRoles(orgUser)).toBe(true)
  })

  it('filters navigation items that the current user cannot access', () => {
    const visibleItems = filterNavigationItems([
      { label: 'Plants', icon: 'mdi-sprout-outline', to: '/plants', roles: ['ROLE_ORG_USER'] },
      { label: 'Billing', icon: 'mdi-credit-card-outline', to: '/billing', roles: ['ROLE_ORG_ADMIN'] },
    ], orgUser)

    expect(visibleItems).toEqual([
      { label: 'Plants', icon: 'mdi-sprout-outline', to: '/plants', roles: ['ROLE_ORG_USER'] },
    ])
  })
})
