import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useBilling } = await import('../composables/billing/useBilling')

describe('useBilling', () => {
  beforeEach(() => vi.clearAllMocks())

  it('lit l\'état d\'abonnement', async () => {
    apiMock.mockResolvedValueOnce({ status: 'trialing', entitled: true, canManage: false, trialEndsAt: null })
    await expect(useBilling().subscription()).resolves.toMatchObject({ status: 'trialing' })
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/billing/subscription')
  })

  it('démarre un checkout avec le plan choisi', async () => {
    apiMock.mockResolvedValueOnce({ url: 'https://pay.example/x' })
    await useBilling().checkout('annual')
    const [path, options] = apiMock.mock.calls[0] as [string, { method: string, body: { plan: string } }]
    expect(path).toBe('/api/v1/billing/checkout')
    expect(options.method).toBe('POST')
    expect(options.body.plan).toBe('annual')
  })

  it('ouvre le portail de gestion', async () => {
    apiMock.mockResolvedValueOnce({ url: 'https://portal.example/y' })
    await useBilling().portal()
    const [path, options] = apiMock.mock.calls[0] as [string, { method: string }]
    expect(path).toBe('/api/v1/billing/portal')
    expect(options.method).toBe('POST')
  })
})
