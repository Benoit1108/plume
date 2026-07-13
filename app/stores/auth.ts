import { defineStore } from 'pinia'

interface TokenPair { token: string, refresh_token: string }

const THIRTY_DAYS = 60 * 60 * 24 * 30

/** Extrait un claim string du payload JWT (sans vérification — usage affichage uniquement). */
function jwtClaim(token: string, claim: string): string | null {
  try {
    const payload = token.split('.')[1] ?? ''
    const decoded: unknown = JSON.parse(atob(payload.replace(/-/g, '+').replace(/_/g, '/')))
    const value = (decoded as Record<string, unknown>)[claim]
    return typeof value === 'string' ? value : null
  }
  catch {
    return null
  }
}

/**
 * Authentification JWT (access + refresh, rotation single_use côté serveur).
 * Tokens persistés en cookies `secure` ; l'email est lu dans le JWT (pas de cookie dédié).
 * Durcissement possible plus tard : refresh en cookie httpOnly côté serveur.
 */
export const useAuthStore = defineStore('auth', () => {
  const base = useRuntimeConfig().public.apiBase
  const cookieOptions = { default: () => null, sameSite: 'lax', secure: true, maxAge: THIRTY_DAYS } as const
  const token = useCookie<string | null>('plume_token', cookieOptions)
  const refreshToken = useCookie<string | null>('plume_refresh', cookieOptions)

  const isAuthenticated = computed(() => Boolean(token.value))
  const email = computed(() => (token.value ? jwtClaim(token.value, 'username') : null))

  async function login(mail: string, password: string): Promise<void> {
    const res = await $fetch<TokenPair>('/api/v1/login_check', {
      baseURL: base,
      method: 'POST',
      body: { email: mail, password },
    })
    token.value = res.token
    refreshToken.value = res.refresh_token
  }

  // Mutex : plusieurs 401 simultanés partagent LE même refresh (indispensable
  // avec la rotation single_use — un refresh concurrent invaliderait l'autre).
  let refreshing: Promise<boolean> | null = null

  function tryRefresh(): Promise<boolean> {
    refreshing ??= doRefresh().finally(() => {
      refreshing = null
    })
    return refreshing
  }

  async function doRefresh(): Promise<boolean> {
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
    // Révocation côté serveur (fire-and-forget) : le refresh token ne survit pas au logout.
    const currentRefresh = refreshToken.value
    if (currentRefresh) {
      void $fetch('/api/v1/token/invalidate', {
        baseURL: base,
        method: 'POST',
        body: { refresh_token: currentRefresh },
      }).catch(() => { /* déjà expiré/révoqué : rien à faire */ })
    }

    token.value = null
    refreshToken.value = null
    void navigateTo('/login')
  }

  return { token, refreshToken, email, isAuthenticated, login, tryRefresh, logout }
})
