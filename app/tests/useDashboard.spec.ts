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
})
