import { beforeEach, describe, expect, it, vi } from 'vitest'

const fetchMock = vi.fn()
const auth = {
  token: 'tok-1' as string | null,
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

describe('useApi', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    auth.token = 'tok-1'
  })

  it('ajoute le Bearer token et l\'Accept JSON-LD', async () => {
    fetchMock.mockResolvedValueOnce({ ok: true })

    await useApi()('/api/v1/organizations')

    const [path, options] = fetchMock.mock.calls[0] as [string, { headers: Record<string, string> }]
    expect(path).toBe('/api/v1/organizations')
    expect(options.headers.Authorization).toBe('Bearer tok-1')
    expect(options.headers.Accept).toBe('application/ld+json')
  })

  it('sur 401 : refresh puis UNE relance avec le nouveau token', async () => {
    fetchMock.mockRejectedValueOnce(http401()).mockResolvedValueOnce({ ok: true })
    auth.tryRefresh.mockImplementationOnce(async () => {
      auth.token = 'tok-2'
      return true
    })

    const result = await useApi()<{ ok: boolean }>('/api/v1/organizations')

    expect(result).toEqual({ ok: true })
    expect(auth.tryRefresh).toHaveBeenCalledTimes(1)
    const retryOptions = fetchMock.mock.calls[1]?.[1] as { headers: Record<string, string> }
    expect(retryOptions.headers.Authorization).toBe('Bearer tok-2')
  })

  it('sur 401 avec refresh en échec : logout et propagation de l\'erreur', async () => {
    fetchMock.mockRejectedValueOnce(http401())
    auth.tryRefresh.mockResolvedValueOnce(false)

    await expect(useApi()('/api/v1/organizations')).rejects.toThrow()
    expect(auth.logout).toHaveBeenCalledTimes(1)
  })

  it('un second 401 après refresh ne boucle pas (une seule relance)', async () => {
    fetchMock.mockRejectedValue(http401())
    auth.tryRefresh.mockResolvedValue(true)

    await expect(useApi()('/api/v1/organizations')).rejects.toThrow()
    expect(fetchMock).toHaveBeenCalledTimes(2)
    expect(auth.tryRefresh).toHaveBeenCalledTimes(1)
  })

  it('une erreur non-401 est propagée sans refresh ni logout', async () => {
    fetchMock.mockRejectedValueOnce(Object.assign(new Error('500'), { response: { status: 500 } }))

    await expect(useApi()('/api/v1/organizations')).rejects.toThrow()
    expect(auth.tryRefresh).not.toHaveBeenCalled()
    expect(auth.logout).not.toHaveBeenCalled()
  })
})
