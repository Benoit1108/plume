import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useDashboard } = await import('../composables/useDashboard')

describe('useDashboard', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('lit la ressource singleton en JSON-LD', async () => {
    apiMock.mockResolvedValueOnce({
      contacted: 3,
      replied: 2,
      won: 1,
      lost: 1,
      activeLeads: 2,
      outreachThisMonth: 3,
      weeklyTarget: 5,
      pipeline: [{ status: 'TO_CONTACT', count: 1 }],
      weeklyActivity: [{ weekStart: '2026-07-13', acts: 3 }],
      segments: [{ segment: 'PUBLISHING', contacted: 1, replied: 1, won: 1 }],
    })

    const board = await useDashboard().get()

    expect(board.contacted).toBe(3)
    expect(board.pipeline).toHaveLength(1)
    const [path, options] = apiMock.mock.calls[0] as [string, { headers: Record<string, string> }]
    expect(path).toBe('/api/v1/dashboard')
    expect(options.headers.Accept).toBe('application/ld+json')
  })

  it('exporte le CSV en blob', async () => {
    apiMock.mockResolvedValueOnce(new Blob())
    await useDashboard().exportCsv()
    const [path, options] = apiMock.mock.calls[0] as [string, { responseType: string }]
    expect(path).toBe('/api/v1/dashboard/export')
    expect(options.responseType).toBe('blob')
  })

  it('passe la période en query quand elle restreint (all = URL propre)', async () => {
    apiMock.mockResolvedValue({ contacted: 0 })

    await useDashboard().get('30d')
    await useDashboard().get('all')
    await useDashboard().exportCsv('90d')

    const [, restricted] = apiMock.mock.calls[0] as [string, { query: Record<string, string> }]
    const [, allTime] = apiMock.mock.calls[1] as [string, { query: Record<string, string> }]
    const [, exported] = apiMock.mock.calls[2] as [string, { query: Record<string, string> }]
    expect(restricted.query).toEqual({ period: '30d' })
    expect(allTime.query).toEqual({}) // « depuis le début » ne pollue pas l'URL
    expect(exported.query).toEqual({ period: '90d' })
  })
})
