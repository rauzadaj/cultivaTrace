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
  const isAdmin = computed(() => user.value?.roles.includes('ROLE_ADMIN') ?? false)
  const isManager = computed(() => user.value?.roles.some(r => ['ROLE_ADMIN', 'ROLE_MANAGER'].includes(r)) ?? false)
  const isOperator = computed(() => user.value?.roles.some(r => ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_OPERATOR'].includes(r)) ?? false)

  const organization = computed(() => user.value?.organization ?? null)

  const isPlanActive = computed(() => organization.value?.licenseStatus === 'active')

  const hasIoT = computed(() => {
    const plan = organization.value?.plan
    return plan === 'pro' || plan === 'business' || plan === 'enterprise'
  })

  // ── Actions ──────────────────────────────────────────────────────────────
  async function login(email: string, password: string): Promise<void> {
    loading.value = true
    try {
      const { data } = await authApi.login({ email, password })
      token.value = data.token
      localStorage.setItem(TOKEN_KEY, data.token)
      localStorage.removeItem(LEGACY_TOKEN_KEY)
      if (data.refresh_token) {
        localStorage.setItem('refresh_token', data.refresh_token)
      }
      await fetchMe()
    } finally {
      loading.value = false
    }
  }

  async function fetchMe(): Promise<void> {
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
    localStorage.removeItem('refresh_token')
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
    isAuthenticated, isAdmin, isManager, isOperator,
    organization, isPlanActive, hasIoT,
    login, logout, fetchMe, init, hasRole,
  }
})
