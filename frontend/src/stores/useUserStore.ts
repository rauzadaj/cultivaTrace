import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

const API_BASE_KEY = 'cultivatrace_api_base'
const TOKEN_KEY = 'cultivatrace_token'
const OPERATOR_KEY = 'cultivatrace_operator'

function resolveInitialApiBaseUrl(): string {
  const storedValue = window.localStorage.getItem(API_BASE_KEY)?.trim()

  if (!storedValue) {
    return '/api'
  }

  const normalizedStoredValue = storedValue.replace(/\/+$/, '')
  const isLocalProxyContext = window.location.hostname === 'localhost' && window.location.port === '5173'

  if (isLocalProxyContext && normalizedStoredValue === 'http://localhost:8000/api') {
    window.localStorage.setItem(API_BASE_KEY, '/api')

    return '/api'
  }

  return storedValue
}

export const useUserStore = defineStore('user', () => {
  const apiBaseUrl = ref(resolveInitialApiBaseUrl())
  const token = ref(window.localStorage.getItem(TOKEN_KEY) ?? '')
  const operatorLabel = ref(window.localStorage.getItem(OPERATOR_KEY) ?? 'Field Operator')

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

  function setOperatorLabel(nextLabel: string) {
    operatorLabel.value = nextLabel.trim() || 'Field Operator'
    window.localStorage.setItem(OPERATOR_KEY, operatorLabel.value)
  }

  return {
    apiBaseUrl,
    token,
    operatorLabel,
    normalizedApiBase,
    isAuthenticated,
    setApiBaseUrl,
    setToken,
    setOperatorLabel,
  }
})
