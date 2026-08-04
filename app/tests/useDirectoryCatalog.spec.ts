import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useDirectoryCatalog } = await import('../composables/directory/useDirectoryCatalog')

describe('useDirectoryCatalog', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('list transmet la recherche et déballe les entrées', async () => {
    apiMock.mockResolvedValueOnce({ entries: [{ id: 'sample-1', name: 'Éditions Exemple' }] })
    await expect(useDirectoryCatalog().list('édit')).resolves.toHaveLength(1)
    const [path, options] = apiMock.mock.calls[0] as [string, { query: Record<string, string> }]
    expect(path).toBe('/api/v1/directory/catalog')
    expect(options.query).toEqual({ q: 'édit' })

    apiMock.mockResolvedValueOnce({ entries: [] })
    await useDirectoryCatalog().list()
    expect((apiMock.mock.calls[1] as [string, { query: Record<string, string> }])[1].query).toEqual({})
  })

  it('add poste l\'id sur l\'import', async () => {
    apiMock.mockResolvedValueOnce({ name: 'Éditions Exemple' })
    await useDirectoryCatalog().add('sample-1')
    const [path, options] = apiMock.mock.calls[0] as [string, { method: string, body: { id: string } }]
    expect(path).toBe('/api/v1/directory/catalog/import')
    expect(options.method).toBe('POST')
    expect(options.body).toEqual({ id: 'sample-1' })
  })
})
