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

/** JWT factice (non signé) portant un claim username. */
function fakeJwt(username: string): string {
  const payload = btoa(JSON.stringify({ username })).replace(/\+/g, '-').replace(/\//g, '_')
  return `header.${payload}.sig`
}

describe('auth store', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    cookies.clear()
    setActivePinia(createPinia())
  })

  it('login stocke la paire de tokens et expose l\'email depuis le JWT', async () => {
    fetchMock.mockResolvedValueOnce({ token: fakeJwt('marie@plume.fr'), refresh_token: 'r1' })
    const auth = useAuthStore()

    await auth.login('marie@plume.fr', 'secret')

    expect(auth.isAuthenticated).toBe(true)
    expect(auth.refreshToken).toBe('r1')
    expect(auth.email).toBe('marie@plume.fr')
  })

  it('tryRefresh applique la rotation (nouveau refresh token stocké)', async () => {
    fetchMock.mockResolvedValueOnce({ token: fakeJwt('a@b.fr'), refresh_token: 'r2' })
    const auth = useAuthStore()
    auth.refreshToken = 'r1'

    await expect(auth.tryRefresh()).resolves.toBe(true)
    expect(auth.refreshToken).toBe('r2')
  })

  it('mutualise les refresh concurrents (un seul appel réseau)', async () => {
    let release!: (value: { token: string, refresh_token: string }) => void
    fetchMock.mockReturnValueOnce(new Promise((resolve) => {
      release = resolve
    }))
    const auth = useAuthStore()
    auth.refreshToken = 'r1'

    const first = auth.tryRefresh()
    const second = auth.tryRefresh()
    release({ token: fakeJwt('a@b.fr'), refresh_token: 'r2' })

    await expect(Promise.all([first, second])).resolves.toEqual([true, true])
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('tryRefresh sans refresh token répond false sans appel réseau', async () => {
    const auth = useAuthStore()

    await expect(auth.tryRefresh()).resolves.toBe(false)
    expect(fetchMock).not.toHaveBeenCalled()
  })

  it('logout révoque le refresh token côté serveur et purge les cookies', async () => {
    fetchMock.mockResolvedValueOnce(undefined)
    const auth = useAuthStore()
    auth.token = fakeJwt('a@b.fr')
    auth.refreshToken = 'r1'

    auth.logout()

    expect(auth.token).toBeNull()
    expect(auth.refreshToken).toBeNull()
    const [path, options] = fetchMock.mock.calls[0] as [string, { body: { refresh_token: string } }]
    expect(path).toBe('/api/v1/token/invalidate')
    expect(options.body.refresh_token).toBe('r1')
    expect(navigateToMock).toHaveBeenCalledWith('/login')
  })
})
