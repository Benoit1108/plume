import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)
vi.stubGlobal('useState', (_key: string, init: () => unknown) => ({ value: init() }))

const { useSourcing } = await import('../composables/useSourcing')

describe('useSourcing', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('queue lit la file et met à jour le compteur partagé', async () => {
    apiMock.mockResolvedValueOnce({ member: [{ id: '1' }, { id: '2' }] })
    const sourcing = useSourcing()

    await expect(sourcing.queue()).resolves.toHaveLength(2)
    expect(sourcing.pendingCount.value).toBe(2)
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/candidate-leads')
  })

  it('queue accepte aussi hydra:member (compat)', async () => {
    apiMock.mockResolvedValueOnce({ 'hydra:member': [{ id: '1' }] })
    await expect(useSourcing().queue()).resolves.toHaveLength(1)
  })

  it('refreshCount avale les erreurs (badge best-effort)', async () => {
    apiMock.mockRejectedValueOnce(new Error('boom'))
    await expect(useSourcing().refreshCount()).resolves.toBeUndefined()
  })

  it('accept / merge / reject postent sur les bons endpoints', async () => {
    apiMock.mockResolvedValue(undefined)
    const sourcing = useSourcing()

    await sourcing.accept('c1', { organizationName: 'X', organizationType: 'PUBLISHER', languagePair: 'en>fr', segment: 'PUBLISHING', priority: 'MEDIUM', website: null })
    await sourcing.merge('c1', { organizationId: 'o1', languagePair: 'en>fr', segment: 'PUBLISHING', priority: 'MEDIUM' })
    await sourcing.reject('c1')

    const paths = apiMock.mock.calls.map(call => (call as [string])[0])
    expect(paths).toContain('/api/v1/candidate-leads/c1/accept')
    expect(paths).toContain('/api/v1/candidate-leads/c1/merge')
    expect(paths).toContain('/api/v1/candidate-leads/c1/reject')
  })

  it('poll relève la source configurée', async () => {
    apiMock.mockResolvedValue(undefined)

    await useSourcing().poll()

    const [path, options] = apiMock.mock.calls[0] as [string, { method: string }]
    expect(path).toBe('/api/v1/sources/poll')
    expect(options.method).toBe('POST')
  })
})
