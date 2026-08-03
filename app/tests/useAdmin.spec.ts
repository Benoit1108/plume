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

  it('accounts transmet recherche/filtre/tri et déballe la liste', async () => {
    apiMock.mockResolvedValueOnce({ accounts: [{ email: 'a@plume.test' }] })
    await expect(useAdmin().accounts('ali', 'unverified', 'leads')).resolves.toHaveLength(1)
    const [path, options] = apiMock.mock.calls[0] as [string, { query: Record<string, string> }]
    expect(path).toBe('/api/v1/admin/accounts')
    expect(options.query).toEqual({ q: 'ali', status: 'unverified', sort: 'leads' })

    // Sans recherche : `q` est omis, filtre/tri gardent leurs défauts.
    apiMock.mockResolvedValueOnce({ accounts: [] })
    await useAdmin().accounts()
    const [, empty] = apiMock.mock.calls[1] as [string, { query: Record<string, string> }]
    expect(empty.query).toEqual({ status: 'all', sort: 'email' })
  })

  it('audit déballe les entrées (avec et sans filtre d\'action)', async () => {
    apiMock.mockResolvedValueOnce({ entries: [{ id: '1', action: 'account.2fa_reset' }] })
    await expect(useAdmin().audit('account.2fa_reset')).resolves.toHaveLength(1)
    const [path, options] = apiMock.mock.calls[0] as [string, { query: Record<string, string> }]
    expect(path).toBe('/api/v1/admin/audit')
    expect(options.query).toEqual({ action: 'account.2fa_reset' })

    apiMock.mockResolvedValueOnce({ entries: [] })
    await useAdmin().audit()
    const [, noFilter] = apiMock.mock.calls[1] as [string, { query: Record<string, string> }]
    expect(noFilter.query).toEqual({})
  })

  it('accountsExport renvoie un blob CSV avec les filtres', async () => {
    apiMock.mockResolvedValueOnce(new Blob())
    await useAdmin().accountsExport('ali', 'deleting', 'created')
    const [path, options] = apiMock.mock.calls[0] as [string, { responseType: string, query: Record<string, string> }]
    expect(path).toBe('/api/v1/admin/accounts/export')
    expect(options.responseType).toBe('blob')
    expect(options.query).toEqual({ q: 'ali', status: 'deleting', sort: 'created' })

    // Sans recherche : `q` omis.
    apiMock.mockResolvedValueOnce(new Blob())
    await useAdmin().accountsExport()
    const [, noQ] = apiMock.mock.calls[1] as [string, { query: Record<string, string> }]
    expect(noQ.query).toEqual({ status: 'all', sort: 'email' })
  })

  it('alerts lit la santé (inactifs, boîtes en erreur, vérifs en souffrance)', async () => {
    apiMock.mockResolvedValueOnce({ inactiveAccounts: [], mailboxesInError: [{ email: 'x@plume.test' }], stuckVerification: [] })
    await expect(useAdmin().alerts()).resolves.toMatchObject({ mailboxesInError: [{ email: 'x@plume.test' }] })
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/admin/alerts')
  })

  it('accountDetail lit la fiche d\'un compte', async () => {
    apiMock.mockResolvedValueOnce({ email: 'x@plume.test', leads: 3 })
    await expect(useAdmin().accountDetail('t-9')).resolves.toMatchObject({ leads: 3 })
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/admin/accounts/t-9')
  })

  it('trends lit la croissance (actifs/semaine + entonnoir)', async () => {
    apiMock.mockResolvedValueOnce({ weeklyActive: [], funnel: { signedUp: 5, verified: 3, activated: 2, active30d: 1 } })
    await expect(useAdmin().trends()).resolves.toMatchObject({ funnel: { signedUp: 5 } })
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/admin/trends')
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
