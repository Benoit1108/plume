import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useToday } = await import('../composables/useToday')
const { useProfile } = await import('../composables/useProfile')

describe('useToday / useProfile', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('today lit la ressource singleton', async () => {
    apiMock.mockResolvedValueOnce({ followUpsDue: [], toContact: [], weeklyTarget: 5, weeklyDone: 2, streak: 1 })

    const board = await useToday().get()

    expect(board.weeklyDone).toBe(2)
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/today')
  })

  it('profile : lecture et mise à jour de l\'objectif en merge-patch', async () => {
    apiMock.mockResolvedValue({ weeklyGoal: 8, timezone: 'Europe/Paris' })
    const profile = useProfile()

    await profile.get()
    await profile.updateWeeklyGoal(8)

    const [path, options] = apiMock.mock.calls[1] as [string, { method: string, body: { weeklyGoal: number }, headers: Record<string, string> }]
    expect(path).toBe('/api/v1/profile')
    expect(options.method).toBe('PATCH')
    expect(options.body.weeklyGoal).toBe(8)
    expect(options.headers['Content-Type']).toBe('application/merge-patch+json')
  })

  it('profile.update patch la présentation complète (bio, spécialités, signature)', async () => {
    apiMock.mockResolvedValue({ weeklyGoal: 5, timezone: 'Europe/Paris', bio: 'Bio.' })

    await useProfile().update({ weeklyGoal: 5, bio: 'Bio.', specialties: null, signature: 'Marie' })

    const [path, options] = apiMock.mock.calls[0] as [string, { method: string, body: Record<string, unknown>, headers: Record<string, string> }]
    expect(path).toBe('/api/v1/profile')
    expect(options.method).toBe('PATCH')
    expect(options.headers['Content-Type']).toBe('application/merge-patch+json')
    expect(options.body).toEqual({ weeklyGoal: 5, bio: 'Bio.', specialties: null, signature: 'Marie' })
  })
})
