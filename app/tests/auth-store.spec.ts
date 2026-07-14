import { beforeEach, describe, expect, it, vi } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { computed, ref } from 'vue'

const fetchMock = vi.fn()
const navigateToMock = vi.fn()
const cookies = new Map<string, ReturnType<typeof ref<string | null>>>()

vi.stubGlobal('$fetch', fetchMock)
vi.stubGlobal('navigateTo', navigateToMock)
vi.stubGlobal('computed', computed)
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '' } }))
vi.stubGlobal('useCookie', (name: string) => {
  if (!cookies.has(name)) cookies.set(name, ref<string | null>(null))
  return cookies.get(name)
})

const { useAuthStore } = await import('../stores/auth')

describe('auth store (cookies httpOnly — M2.0)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    cookies.clear()
    setActivePinia(createPinia())
  })

  it('login authentifie puis apprend son identité via /me — aucun token ne touche le JS', async () => {
    fetchMock.mockResolvedValueOnce(undefined) // login_check : cookies posés par la réponse
    fetchMock.mockResolvedValueOnce({ email: 'marie@plume.fr' }) // /me
    const auth = useAuthStore()

    await auth.login('marie@plume.fr', 'secret')

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.email).toBe('marie@plume.fr')
    const paths = fetchMock.mock.calls.map(call => call[0] as string)
    expect(paths).toEqual(['/api/v1/login_check', '/api/v1/me'])
    // Rien qui ressemble à un token stocké côté JS (le legacy est lu pour être purgé, jamais écrit).
    expect(cookies.get('plume_token')?.value ?? null).toBeNull()
    expect(cookies.get('plume_refresh')?.value ?? null).toBeNull()
    expect(cookies.get('plume_email')?.value).toBe('marie@plume.fr')
  })

  it('purge les anciens cookies de tokens lisibles (migration M2.0)', () => {
    const legacy = ref<string | null>('vieux-jwt')
    cookies.set('plume_token', legacy)
    useAuthStore()

    expect(legacy.value).toBeNull()
  })

  it('tryRefresh mutualise les appels concurrents (rotation single_use)', async () => {
    let resolveRefresh: (value: unknown) => void = () => {}
    fetchMock.mockReturnValueOnce(new Promise((resolve) => {
      resolveRefresh = resolve
    }))
    const auth = useAuthStore()

    const first = auth.tryRefresh()
    const second = auth.tryRefresh()
    resolveRefresh(undefined)

    await expect(first).resolves.toBe(true)
    await expect(second).resolves.toBe(true)
    expect(fetchMock).toHaveBeenCalledTimes(1) // UN seul POST /token/refresh
    expect((fetchMock.mock.calls[0] as [string])[0]).toBe('/api/v1/token/refresh')
  })

  it('tryRefresh rend false quand le serveur refuse (session vraiment morte)', async () => {
    fetchMock.mockRejectedValueOnce(new Error('401'))
    const auth = useAuthStore()

    await expect(auth.tryRefresh()).resolves.toBe(false)
  })

  it('logout révoque côté serveur, oublie l\'email et route vers /login', async () => {
    fetchMock.mockResolvedValueOnce(undefined)
    const auth = useAuthStore()
    cookies.get('plume_email')!.value = 'marie@plume.fr'

    auth.logout()

    expect((fetchMock.mock.calls[0] as [string])[0]).toBe('/api/v1/token/invalidate')
    expect(auth.email).toBeNull()
    expect(auth.isAuthenticated).toBe(false)
    expect(navigateToMock).toHaveBeenCalledWith('/login')
  })

  it('logout reste silencieux si la révocation échoue (token déjà mort)', async () => {
    fetchMock.mockRejectedValueOnce(new Error('réseau'))
    const auth = useAuthStore()

    expect(() => auth.logout()).not.toThrow()
    await Promise.resolve() // le rejet est absorbé en arrière-plan
    expect(navigateToMock).toHaveBeenCalledWith('/login')
  })
})
