const TOKEN_KEY = 'oa_access_token'
const USER_KEY = 'oa_user_info'

export function getToken(): string | null {
  const token = sessionStorage.getItem(TOKEN_KEY)
  if (token) return token
  const legacyToken = localStorage.getItem(TOKEN_KEY)
  if (legacyToken) {
    localStorage.removeItem(TOKEN_KEY)
    localStorage.removeItem(USER_KEY)
  }
  return null
}

export function setToken(token: string): void {
  sessionStorage.setItem(TOKEN_KEY, token)
}

export function removeToken(): void {
  sessionStorage.removeItem(TOKEN_KEY)
  localStorage.removeItem(TOKEN_KEY)
}

export function getUserInfo(): Record<string, unknown> {
  localStorage.removeItem(USER_KEY)
  const data = sessionStorage.getItem(USER_KEY)
  return data ? JSON.parse(data) : null
}

export function setUserInfo(info: Record<string, unknown>): void {
  sessionStorage.setItem(USER_KEY, JSON.stringify(info))
}

export function removeUserInfo(): void {
  sessionStorage.removeItem(USER_KEY)
  localStorage.removeItem(USER_KEY)
}

export function clearAuth(): void {
  removeToken()
  removeUserInfo()
}
