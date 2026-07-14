import { beforeEach, describe, expect, it, vi } from 'vitest'

const fetchMock = vi.fn()
const auth = {
  tryRefresh: vi.fn<() => Promise<boolean>>(),
  logout: vi.fn(),
}

vi.stubGlobal('$fetch', fetchMock)
vi.stubGlobal('useRuntimeConfig', () => ({ public: { apiBase: '' } }))
vi.stubGlobal('useAuthStore', () => auth)

const { useApi } = await import('../composables/useApi')

function http401(): Error {
  return Object.assign(new Error('401'), { response: { status: 401 } })
}

describe('useApi (cookies httpOnly — M2.0)', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('n\'ajoute AUCUN header d\'auth (les cookies httpOnly font le travail) mais garde Accept JSON-LD', async () => {
    fetchMock.mockResolvedValueOnce({ ok: true })

    await useApi()('/api/v1/organizations')

    const [path, options] = fetchMock.mock.calls[0] as [string, { headers: Record<string, string> }]
    expect(path).toBe('/api/v1/organizations')
    expect(options.headers.Authorization).toBeUndefined()
    expect(options.headers.Accept).toBe('application/ld+json')
  })

  it('sur 401 : refresh (cookie) puis UNE relance', async () => {
    fetchMock.mockRejectedValueOnce(http401()).mockResolvedValueOnce({ ok: true })
    auth.tryRefresh.mockResolvedValueOnce(true)

    const result = await useApi()<{ ok: boolean }>('/api/v1/leads')

    expect(result.ok).toBe(true)
    expect(auth.tryRefresh).toHaveBeenCalledTimes(1)
    expect(fetchMock).toHaveBeenCalledTimes(2)
  })

  it('ne boucle pas : un 401 APRÈS refresh déconnecte', async () => {
    fetchMock.mockRejectedValue(http401())
    auth.tryRefresh.mockResolvedValue(true)

    await expect(useApi()('/api/v1/leads')).rejects.toThrow()

    expect(fetchMock).toHaveBeenCalledTimes(2) // jamais une 3e tentative
    expect(auth.logout).toHaveBeenCalledTimes(1)
  })

  it('401 avec refresh impossible : déconnexion immédiate', async () => {
    fetchMock.mockRejectedValueOnce(http401())
    auth.tryRefresh.mockResolvedValueOnce(false)

    await expect(useApi()('/api/v1/leads')).rejects.toThrow()
    expect(auth.logout).toHaveBeenCalledTimes(1)
    expect(fetchMock).toHaveBeenCalledTimes(1)
  })

  it('les autres erreurs remontent sans refresh ni logout', async () => {
    fetchMock.mockRejectedValueOnce(Object.assign(new Error('500'), { response: { status: 500 } }))

    await expect(useApi()('/api/v1/leads')).rejects.toThrow('500')
    expect(auth.tryRefresh).not.toHaveBeenCalled()
    expect(auth.logout).not.toHaveBeenCalled()
  })
})
