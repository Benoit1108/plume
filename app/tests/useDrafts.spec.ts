import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useDrafts } = await import('../composables/useDrafts')

describe('useDrafts', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('forLead normalise la collection JSON-LD (member / hydra:member / vide)', async () => {
    apiMock.mockResolvedValueOnce({ member: [{ id: 'd1', status: 'READY' }] })
    await expect(useDrafts().forLead('l1')).resolves.toHaveLength(1)
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/leads/l1/drafts')

    apiMock.mockResolvedValueOnce({ 'hydra:member': [{ id: 'd1' }] })
    await expect(useDrafts().forLead('l1')).resolves.toHaveLength(1)

    apiMock.mockResolvedValueOnce({})
    await expect(useDrafts().forLead('l1')).resolves.toEqual([])
  })

  it('generate poste type, langue cible et modèle optionnel sous la piste', async () => {
    apiMock.mockResolvedValueOnce({ id: 'd1', status: 'GENERATING' })

    await useDrafts().generate('l1', { type: 'APPLICATION_EMAIL', targetLanguage: 'fr', templateId: null })

    const [path, options] = apiMock.mock.calls[0] as [string, { method: string, body: { type: string, targetLanguage: string, templateId: string | null } }]
    expect(path).toBe('/api/v1/leads/l1/drafts')
    expect(options.method).toBe('POST')
    expect(options.body).toEqual({ type: 'APPLICATION_EMAIL', targetLanguage: 'fr', templateId: null })
  })

  it('edit patch le sujet/corps en merge-patch, regenerate et remove visent le brouillon', async () => {
    apiMock.mockResolvedValue({ id: 'd1' })
    const drafts = useDrafts()

    await drafts.edit('d1', { subject: 'Objet', body: 'Corps' })
    const [editPath, editOptions] = apiMock.mock.calls[0] as [string, { method: string, headers: Record<string, string> }]
    expect(editPath).toBe('/api/v1/drafts/d1')
    expect(editOptions.method).toBe('PATCH')
    expect(editOptions.headers['Content-Type']).toBe('application/merge-patch+json')

    await drafts.regenerate('d1')
    expect((apiMock.mock.calls[1] as [string])[0]).toBe('/api/v1/drafts/d1/regenerate')

    await drafts.remove('d1')
    const [removePath, removeOptions] = apiMock.mock.calls[2] as [string, { method: string }]
    expect(removePath).toBe('/api/v1/drafts/d1')
    expect(removeOptions.method).toBe('DELETE')
  })

  it('send poste l\'envoi asynchrone du brouillon', async () => {
    apiMock.mockResolvedValueOnce({ id: 'out-1', status: 'SENDING' })

    const receipt = await useDrafts().send('d1')

    expect(receipt.status).toBe('SENDING')
    const [path, options] = apiMock.mock.calls[0] as [string, { method: string }]
    expect(path).toBe('/api/v1/drafts/d1/send')
    expect(options.method).toBe('POST')
  })

  it('templates : liste (seed côté API) et CRUD complet', async () => {
    apiMock.mockResolvedValueOnce({ member: [{ id: 't1', name: 'Candidature édition (FR)' }] })
    const drafts = useDrafts()
    await expect(drafts.templates()).resolves.toHaveLength(1)
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/templates')

    apiMock.mockResolvedValue({ id: 't2' })
    const input = { name: 'N', type: 'APPLICATION_EMAIL', segment: 'PUBLISHING', language: 'fr', subject: null, body: 'B' } as const
    await drafts.createTemplate(input)
    expect((apiMock.mock.calls[1] as [string, { method: string }])[1].method).toBe('POST')

    await drafts.updateTemplate('t2', input)
    const [updatePath, updateOptions] = apiMock.mock.calls[2] as [string, { method: string }]
    expect(updatePath).toBe('/api/v1/templates/t2')
    expect(updateOptions.method).toBe('PATCH')

    await drafts.removeTemplate('t2')
    expect((apiMock.mock.calls[3] as [string, { method: string }])[1].method).toBe('DELETE')
  })
})
