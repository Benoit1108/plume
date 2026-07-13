import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useDirectory } = await import('../composables/useDirectory')

describe('useDirectory', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('list normalise la collection JSON-LD (clé member)', async () => {
    apiMock.mockResolvedValueOnce({ member: [{ id: '1', name: 'Actes Sud' }] })

    const result = await useDirectory().list()

    expect(result).toHaveLength(1)
    expect(result[0]?.name).toBe('Actes Sud')
  })

  it('list retombe sur hydra:member puis sur un tableau vide', async () => {
    apiMock.mockResolvedValueOnce({ 'hydra:member': [{ id: '1', name: 'X' }] })
    await expect(useDirectory().list()).resolves.toHaveLength(1)

    apiMock.mockResolvedValueOnce({})
    await expect(useDirectory().list()).resolves.toEqual([])
  })

  it('list transmet les filtres en query', async () => {
    apiMock.mockResolvedValueOnce({ member: [] })

    await useDirectory().list({ type: 'PUBLISHER', q: 'actes' })

    const [path, options] = apiMock.mock.calls[0] as [string, { query: Record<string, string> }]
    expect(path).toBe('/api/v1/organizations')
    expect(options.query).toEqual({ type: 'PUBLISHER', q: 'actes' })
  })

  it('importCsv poste le contenu brut', async () => {
    apiMock.mockResolvedValueOnce({ imported: 1, skipped: 0, failed: 0, errors: [] })

    await useDirectory().importCsv('nom\nActes Sud')

    const [path, options] = apiMock.mock.calls[0] as [string, { method: string, body: { content: string } }]
    expect(path).toBe('/api/v1/organizations/import')
    expect(options.method).toBe('POST')
    expect(options.body.content).toBe('nom\nActes Sud')
  })

  it('removeContact vise la bonne sous-ressource en DELETE', async () => {
    apiMock.mockResolvedValueOnce(undefined)

    await useDirectory().removeContact('org-1', 'c-1')

    const [path, options] = apiMock.mock.calls[0] as [string, { method: string }]
    expect(path).toBe('/api/v1/organizations/org-1/contacts/c-1')
    expect(options.method).toBe('DELETE')
  })

  it('les écritures visent les bonnes routes avec les bons verbes', async () => {
    apiMock.mockResolvedValue({})
    const directory = useDirectory()

    await directory.get('org-1')
    await directory.create({ name: 'X' })
    await directory.update('org-1', { name: 'Y' })
    await directory.addContact('org-1', { fullName: 'Z' })
    await directory.updateContact('org-1', 'c-1', { fullName: 'Z2' })

    const calls = apiMock.mock.calls.map(([path, options]) => [path, (options as { method?: string } | undefined)?.method ?? 'GET'])
    expect(calls).toEqual([
      ['/api/v1/organizations/org-1', 'GET'],
      ['/api/v1/organizations', 'POST'],
      ['/api/v1/organizations/org-1', 'PATCH'],
      ['/api/v1/organizations/org-1/contacts', 'POST'],
      ['/api/v1/organizations/org-1/contacts/c-1', 'PATCH'],
    ])
  })
})
