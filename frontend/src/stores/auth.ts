/**
 * apps/frontend/src/stores/auth.ts
 *
 * Authentication store.
 * Manages the JWT, the logged-in user, and the organization.
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/services/api'
import type { JwtPayload, OrganizationRegistrationPayload, User, UserRole } from '@/types/api'
import { clearAuthTokens, getAccessToken, setAuthTokens } from '@/services/authSession'
import { canAccessRoles } from '@/router/access'

export const useAuthStore = defineStore('auth', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const user = ref<User | null>(null)
  const token = ref<string | null>(getAccessToken())
  const loading = ref(false)

  // ── Getters ──────────────────────────────────────────────────────────────
  const isAuthenticated = computed(() => !!token.value)
  const isSuperAdmin = computed(() => user.value?.roles.includes('ROLE_SUPER_ADMIN') ?? false)
  const isOrgAdmin = computed(() => user.value?.roles.some((r: UserRole) => ['ROLE_SUPER_ADMIN', 'ROLE_ORG_ADMIN'].includes(r)) ?? false)
  const isOrgUser = computed(() => user.value?.roles.some((r: UserRole) => ['ROLE_SUPER_ADMIN', 'ROLE_ORG_ADMIN', 'ROLE_ORG_USER'].includes(r)) ?? false)
  const isApiUser = computed(() => user.value?.roles.includes('ROLE_API') ?? false)

  const organization = computed(() => user.value?.organization ?? null)

  const isPlanActive = computed(() => organization.value?.licenseStatus === 'active')

  const hasIoT = computed(() => {
    const plan = organization.value?.plan
    return plan === 'pro' || plan === 'business' || plan === 'enterprise'
  })

  function syncTokenFromStorage(): void {
    const storedToken = getAccessToken()

    if (storedToken && storedToken !== token.value) {
      token.value = storedToken
    }
  }

  // ── Actions ──────────────────────────────────────────────────────────────
  async function login(email: string, password: string): Promise<void> {
    loading.value = true
    try {
      const { data } = await authApi.login({ email, password })
      setAuthTokens({
        token: data.token,
        refreshToken: data.refreshToken,
      })
      token.value = data.token
      await fetchMe()
    } finally {
      loading.value = false
    }
  }

  async function registerOrganization(payload: OrganizationRegistrationPayload): Promise<string> {
    loading.value = true

    try {
      const { data } = await authApi.registerOrganization(payload)
      setAuthTokens({
        token: data.token,
        refreshToken: data.refreshToken,
      })
      token.value = data.token
      await fetchMe()

      return data.nextPath
    } finally {
      loading.value = false
    }
  }

  async function fetchMe(): Promise<void> {
    syncTokenFromStorage()

    if (!token.value) {
      user.value = null
      return
    }

    try {
      const { data } = await authApi.me()
      user.value = data
    } catch {
      // Do not fall back to unverified JWT payload for roles — logout instead
      user.value = null
      token.value = null
      clearAuthTokens()
    }
  }

  async function logout(): Promise<void> {
    // Capture token before clearing — needed for the revocation request header
    const currentToken = token.value
    // Clear local state synchronously so the router and UI react immediately
    user.value = null
    token.value = null
    clearAuthTokens()
    // Best-effort server-side revocation using the captured token (storage is already
    // empty, so the Axios interceptor would not inject the header without it)
    if (currentToken) {
      try {
        await authApi.logout(currentToken)
      } catch {
        // network failure is non-fatal
      }
    }
  }

  function hasRole(role: UserRole): boolean {
    return user.value?.roles.includes(role) ?? false
  }

  function hasAnyRole(roles: readonly UserRole[]): boolean {
    return canAccessRoles(user.value, roles)
  }

  function buildUserFromToken(jwt: string): User {
    const payload = parseJwtPayload(jwt)

    return {
      id: '',
      email: payload.username ?? '',
      roles: payload.roles ?? [],
      mfaEnabled: false,
    }
  }

  function parseJwtPayload(jwt: string): JwtPayload {
    const [, rawPayload = ''] = jwt.split('.')
    const normalizedPayload = rawPayload
      .replace(/-/g, '+')
      .replace(/_/g, '/')
      .padEnd(Math.ceil(rawPayload.length / 4) * 4, '=')

    try {
      return JSON.parse(window.atob(normalizedPayload)) as JwtPayload
    } catch {
      return {}
    }
  }

  // Restore session on app load
  async function init(): Promise<void> {
    syncTokenFromStorage()

    if (token.value) {
      try {
        await fetchMe()
      } catch {
        logout()
      }
    }
  }

  return {
    user, token, loading,
    isAuthenticated, isSuperAdmin, isOrgAdmin, isOrgUser, isApiUser,
    organization, isPlanActive, hasIoT,
    login, registerOrganization, logout, fetchMe, init, hasRole, hasAnyRole,
  }
})
