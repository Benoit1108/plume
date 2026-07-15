import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useAccount } = await import('../composables/useAccount')

describe('useAccount', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('changePassword poste l\'ancien et le nouveau mot de passe', async () => {
    apiMock.mockResolvedValueOnce(undefined)

    await useAccount().changePassword('ancien-Mdp', 'nouveau-Mdp-456')

    const [path, options] = apiMock.mock.calls[0] as [string, { method: string, body: { currentPassword: string, newPassword: string } }]
    expect(path).toBe('/api/v1/account/password')
    expect(options.method).toBe('POST')
    expect(options.body).toEqual({ currentPassword: 'ancien-Mdp', newPassword: 'nouveau-Mdp-456' })
  })
})
