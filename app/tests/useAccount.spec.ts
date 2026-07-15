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

    await useAccount().changePassword('secret-Test-123', 'secret-Test-456')

    const [path, options] = apiMock.mock.calls[0] as [string, { method: string, body: { currentPassword: string, newPassword: string } }]
    expect(path).toBe('/api/v1/account/password')
    expect(options.method).toBe('POST')
    expect(options.body).toEqual({ currentPassword: 'secret-Test-123', newPassword: 'secret-Test-456' })
  })
})
