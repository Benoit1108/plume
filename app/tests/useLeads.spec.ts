import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useLeads } = await import('../composables/useLeads')

describe('useLeads', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('list normalise la collection JSON-LD et demande une grande page (kanban)', async () => {
    apiMock.mockResolvedValueOnce({ member: [{ id: 'l1', status: 'TO_CONTACT' }] })

    const result = await useLeads().list({ status: 'TO_CONTACT' })

    expect(result).toHaveLength(1)
    const [path, options] = apiMock.mock.calls[0] as [string, { query: Record<string, string> }]
    expect(path).toBe('/api/v1/leads')
    expect(options.query).toEqual({ itemsPerPage: '200', status: 'TO_CONTACT' })
  })

  it('transition vise le bon endpoint cas d\'usage', async () => {
    apiMock.mockResolvedValueOnce({ id: 'l1', status: 'CONTACTED' })

    await useLeads().transition('l1', 'contact')

    const [path, options] = apiMock.mock.calls[0] as [string, { method: string }]
    expect(path).toBe('/api/v1/leads/l1/contact')
    expect(options.method).toBe('POST')
  })

  it('addNote poste le texte, timeline lit les interactions', async () => {
    apiMock.mockResolvedValueOnce({ text: 'ok' })
    await useLeads().addNote('l1', 'Rappeler jeudi')
    const [notePath, noteOptions] = apiMock.mock.calls[0] as [string, { body: { text: string } }]
    expect(notePath).toBe('/api/v1/leads/l1/notes')
    expect(noteOptions.body.text).toBe('Rappeler jeudi')

    apiMock.mockResolvedValueOnce({ 'hydra:member': [{ id: 'i1', type: 'note' }] })
    await expect(useLeads().timeline('l1')).resolves.toHaveLength(1)
  })

  it('create et get visent les bonnes routes', async () => {
    apiMock.mockResolvedValue({ id: 'l1' })
    const leads = useLeads()

    await leads.create({ organizationId: 'o1', contactId: null, languagePair: 'en>fr', source: 'DIRECT', priority: 'HIGH', segment: 'PUBLISHING' })
    await leads.get('l1')

    const calls = apiMock.mock.calls.map(([path, options]) => [path, (options as { method?: string } | undefined)?.method ?? 'GET'])
    expect(calls).toEqual([
      ['/api/v1/leads', 'POST'],
      ['/api/v1/leads/l1', 'GET'],
    ])
  })
})
