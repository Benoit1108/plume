import { defineStore } from 'pinia'

interface TokenPair { token: string, refresh_token: string }

/**
 * Authentification JWT (access + refresh). Tokens persistés en cookie.
 * Durcissement possible plus tard : refresh en cookie httpOnly côté serveur.
 */
export const useAuthStore = defineStore('auth', () => {
  const base = useRuntimeConfig().public.apiBase
  const token = useCookie<string | null>('plume_token', { default: () => null, sameSite: 'lax' })
  const refreshToken = useCookie<string | null>('plume_refresh', { default: () => null, sameSite: 'lax' })
  const email = useCookie<string | null>('plume_email', { default: () => null, sameSite: 'lax' })

  const isAuthenticated = computed(() => Boolean(token.value))

  async function login(mail: string, password: string): Promise<void> {
    const res = await $fetch<TokenPair>('/api/v1/login_check', {
      baseURL: base,
      method: 'POST',
      body: { email: mail, password },
    })
    token.value = res.token
    refreshToken.value = res.refresh_token
    email.value = mail
  }

  async function tryRefresh(): Promise<boolean> {
    if (!refreshToken.value) return false
    try {
      const res = await $fetch<TokenPair>('/api/v1/token/refresh', {
        baseURL: base,
        method: 'POST',
        body: new URLSearchParams({ refresh_token: refreshToken.value }),
      })
      token.value = res.token
      refreshToken.value = res.refresh_token
      return true
    }
    catch {
      return false
    }
  }

  function logout(): void {
    token.value = null
    refreshToken.value = null
    email.value = null
    void navigateTo('/login')
  }

  return { token, refreshToken, email, isAuthenticated, login, tryRefresh, logout }
})
