import { defineStore } from 'pinia'

const THIRTY_DAYS = 60 * 60 * 24 * 30

/**
 * Authentification par cookies httpOnly (M2.0) : les tokens ne transitent plus
 * par du JS — l'API les pose, les rafraîchit (rotation single_use) et les
 * efface elle-même, en même origine via le proxy /api. Le front ne garde qu'un
 * témoin NON SENSIBLE : l'email affiché, qui sert aussi d'indice « probablement
 * connecté » pour la garde de route. L'autorité reste l'API : 401 → logout.
 */
export const useAuthStore = defineStore('auth', () => {
  const email = useCookie<string | null>('plume_email', {
    default: () => null,
    sameSite: 'lax',
    secure: true,
    maxAge: THIRTY_DAYS,
  })

  // Migration M2.0 : purge des anciens cookies de tokens lisibles par JS.
  const legacyToken = useCookie<string | null>('plume_token')
  const legacyRefresh = useCookie<string | null>('plume_refresh')
  if (legacyToken.value) legacyToken.value = null
  if (legacyRefresh.value) legacyRefresh.value = null

  const isAuthenticated = computed(() => Boolean(email.value))

  async function login(mail: string, password: string): Promise<void> {
    await $fetch('/api/v1/login_check', {
      method: 'POST',
      body: { email: mail, password },
    })
    // Les cookies httpOnly sont posés par la réponse ; on demande à l'API qui on est.
    const me = await $fetch<{ email: string }>('/api/v1/me')
    email.value = me.email
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
    try {
      // Le refresh token voyage dans son cookie httpOnly (path /api/v1/token).
      await $fetch('/api/v1/token/refresh', { method: 'POST', body: {} })
      return true
    }
    catch {
      return false
    }
  }

  function logout(): void {
    // Révocation + effacement des cookies PAR l'API (fire-and-forget).
    void $fetch('/api/v1/token/invalidate', { method: 'POST', body: {} })
      .catch(() => { /* déjà expiré/révoqué : rien à faire */ })

    email.value = null
    void navigateTo('/login')
  }

  return { email, isAuthenticated, login, tryRefresh, logout }
})
