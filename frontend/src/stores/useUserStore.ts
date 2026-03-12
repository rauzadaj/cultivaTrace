import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

const API_BASE_KEY = 'cultivatrace_api_base'
const TOKEN_KEY = 'cultivatrace_token'
const OPERATOR_KEY = 'cultivatrace_operator'
const USER_EMAIL_KEY = 'cultivatrace_user_email'

function resolveInitialApiBaseUrl(): string {
  const storedValue = window.localStorage.getItem(API_BASE_KEY)?.trim()

  if (!storedValue) {
    return '/api'
  }

  const normalizedStoredValue = storedValue.replace(/\/+$/, '')
  const isLocalProxyContext = window.location.hostname === 'localhost' && window.location.port === '5173'

  if (isLocalProxyContext) {
    try {
      const parsedStoredValue = new URL(normalizedStoredValue)
      const normalizedPath = parsedStoredValue.pathname.replace(/\/+$/, '')
      const isLocalBackend =
        ['localhost', '127.0.0.1'].includes(parsedStoredValue.hostname)
        && parsedStoredValue.port === '8000'
        && normalizedPath === '/api'

      if (isLocalBackend) {
        window.localStorage.setItem(API_BASE_KEY, '/api')

        return '/api'
      }
    } catch {
      // Keep non-URL values as-is.
    }
  }

  return storedValue
}

export const useUserStore = defineStore('user', () => {
  const apiBaseUrl = ref(resolveInitialApiBaseUrl())
  const token = ref(window.localStorage.getItem(TOKEN_KEY) ?? '')
  const operatorLabel = ref(window.localStorage.getItem(OPERATOR_KEY) ?? 'Field Operator')
  const userEmail = ref(window.localStorage.getItem(USER_EMAIL_KEY) ?? '')

  const normalizedApiBase = computed(() => apiBaseUrl.value.trim().replace(/\/+$/, ''))
  const isAuthenticated = computed(() => token.value.trim().length > 0)

  function setApiBaseUrl(nextBaseUrl: string) {
    apiBaseUrl.value = nextBaseUrl
    window.localStorage.setItem(API_BASE_KEY, nextBaseUrl)
  }

  function setToken(nextToken: string) {
    token.value = nextToken.trim()

    if (token.value) {
      window.localStorage.setItem(TOKEN_KEY, token.value)
      return
    }

    window.localStorage.removeItem(TOKEN_KEY)
  }

  function setUserEmail(nextEmail: string) {
    userEmail.value = nextEmail.trim().toLowerCase()

    if (userEmail.value) {
      window.localStorage.setItem(USER_EMAIL_KEY, userEmail.value)
      return
    }

    window.localStorage.removeItem(USER_EMAIL_KEY)
  }

  function setOperatorLabel(nextLabel: string) {
    operatorLabel.value = nextLabel.trim() || 'Field Operator'
    window.localStorage.setItem(OPERATOR_KEY, operatorLabel.value)
  }

  function setSession(nextToken: string, nextEmail: string) {
    setToken(nextToken)
    setUserEmail(nextEmail)

    if (!window.localStorage.getItem(OPERATOR_KEY)) {
      setOperatorLabel(nextEmail.split('@')[0] ?? 'Field Operator')
    }
  }

  function clearSession() {
    setToken('')
    setUserEmail('')
  }

  return {
    apiBaseUrl,
    token,
    operatorLabel,
    userEmail,
    normalizedApiBase,
    isAuthenticated,
    setApiBaseUrl,
    setToken,
    setUserEmail,
    setOperatorLabel,
    setSession,
    clearSession,
  }
})
