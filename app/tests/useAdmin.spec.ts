import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useAdmin } = await import('../composables/useAdmin')

describe('useAdmin', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('overview lit les comptages', async () => {
    apiMock.mockResolvedValueOnce({ accounts: { total: 2 }, business: {}, queues: { failed: 0 } })
    await expect(useAdmin().overview()).resolves.toMatchObject({ accounts: { total: 2 } })
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/admin/overview')
  })

  it('accounts transmet la recherche et déballe la liste', async () => {
    apiMock.mockResolvedValueOnce({ accounts: [{ email: 'a@plume.test' }] })
    await expect(useAdmin().accounts('ali')).resolves.toHaveLength(1)
    const [path, options] = apiMock.mock.calls[0] as [string, { query: Record<string, string> }]
    expect(path).toBe('/api/v1/admin/accounts')
    expect(options.query).toEqual({ q: 'ali' })

    apiMock.mockResolvedValueOnce({ accounts: [] })
    await useAdmin().accounts()
    const [, empty] = apiMock.mock.calls[1] as [string, { query: Record<string, string> }]
    expect(empty.query).toEqual({})
  })

  it('status lit l\'état opérationnel', async () => {
    apiMock.mockResolvedValueOnce({ db: 'ok', queues: {}, failed: 0, backlogAgeSeconds: 0, mailboxesError: 0 })
    await expect(useAdmin().status()).resolves.toMatchObject({ db: 'ok' })
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/admin/status')
  })

  it('metrics lit les KPIs produit', async () => {
    apiMock.mockResolvedValueOnce({ accounts: { total: 3, verified: 2, active30d: 1 }, signups: [], leadsByStatus: {}, totals: {} })
    await expect(useAdmin().metrics()).resolves.toMatchObject({ accounts: { active30d: 1 } })
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/admin/metrics')
  })

  it('requestDeletion poste sur le bon tenant', async () => {
    apiMock.mockResolvedValueOnce({})
    await useAdmin().requestDeletion('t-42')
    const [path, options] = apiMock.mock.calls[0] as [string, { method: string }]
    expect(path).toBe('/api/v1/admin/accounts/t-42/request-deletion')
    expect(options.method).toBe('POST')
  })
})
