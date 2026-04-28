import type { User, UserRole } from '@/types/api'

export interface RoleScopedNavigationItem {
  label: string
  icon: string
  to: string
  roles?: readonly UserRole[]
}

export function canAccessRoles(user: User | null, roles?: readonly UserRole[]): boolean {
  if (!roles || roles.length === 0) {
    return true
  }

  if (!user) {
    return false
  }

  return roles.some((role) => user.roles.includes(role))
}

export function filterNavigationItems<T extends RoleScopedNavigationItem>(
  items: readonly T[],
  user: User | null,
): T[] {
  return items.filter((item) => canAccessRoles(user, item.roles))
}
