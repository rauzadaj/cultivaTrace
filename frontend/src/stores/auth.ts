/**
 * apps/frontend/src/stores/auth.ts
 *
 * Store d'authentification.
 * Gère le JWT, l'utilisateur connecté et l'organisation.
 */

import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { authApi } from '@/services/api'
import type { JwtPayload, User, UserRole } from '@/types/api'

const TOKEN_KEY = 'cultivatrace_token'
const LEGACY_TOKEN_KEY = 'jwt_token'

export const useAuthStore = defineStore('auth', () => {
  // ── State ────────────────────────────────────────────────────────────────
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem(TOKEN_KEY) ?? localStorage.getItem(LEGACY_TOKEN_KEY))
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
    const storedToken = localStorage.getItem(TOKEN_KEY) ?? localStorage.getItem(LEGACY_TOKEN_KEY)

    if (storedToken && storedToken !== token.value) {
      token.value = storedToken
    }
  }

  // ── Actions ──────────────────────────────────────────────────────────────
  async function login(email: string, password: string): Promise<void> {
    loading.value = true
    try {
      const { data } = await authApi.login({ email, password })
      token.value = data.token
      localStorage.setItem(TOKEN_KEY, data.token)
      localStorage.removeItem(LEGACY_TOKEN_KEY)
      await fetchMe()
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
      user.value = buildUserFromToken(token.value)
    }
  }

  function logout(): void {
    user.value = null
    token.value = null
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(LEGACY_TOKEN_KEY)
  }

  function hasRole(role: UserRole): boolean {
    return user.value?.roles.includes(role) ?? false
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
    login, logout, fetchMe, init, hasRole,
  }
})
