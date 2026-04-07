const ACCESS_TOKEN_KEY = 'cultivatrace_token'
const REFRESH_TOKEN_KEY = 'cultivatrace_refresh_token'
const LEGACY_TOKEN_KEY = 'jwt_token'
const USER_EMAIL_KEY = 'cultivatrace_user_email'

export interface StoredAuthTokens {
  token: string
  refreshToken?: string
}

export function getAccessToken(): string | null {
  return localStorage.getItem(ACCESS_TOKEN_KEY) ?? localStorage.getItem(LEGACY_TOKEN_KEY)
}

export function getRefreshToken(): string | null {
  return localStorage.getItem(REFRESH_TOKEN_KEY)
}

export function setAuthTokens(tokens: StoredAuthTokens): void {
  localStorage.setItem(ACCESS_TOKEN_KEY, tokens.token)
  localStorage.removeItem(LEGACY_TOKEN_KEY)

  if (tokens.refreshToken) {
    localStorage.setItem(REFRESH_TOKEN_KEY, tokens.refreshToken)
  }
}

export function clearAuthTokens(): void {
  localStorage.removeItem(ACCESS_TOKEN_KEY)
  localStorage.removeItem(REFRESH_TOKEN_KEY)
  localStorage.removeItem(LEGACY_TOKEN_KEY)
  localStorage.removeItem(USER_EMAIL_KEY)
}

export function redirectToAuth(): void {
  if (typeof window === 'undefined') {
    return
  }

  if (window.location.pathname !== '/auth') {
    window.location.href = '/auth'
  }
}
