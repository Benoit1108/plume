import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useAccount } = await import('../composables/useAccount')

describe('useAccount', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  /** Chaque méthode = un appel HTTP fin ; on vérifie chemin + méthode + corps. */
  function callOf(index = 0): [string, { method?: string, body?: Record<string, unknown>, responseType?: string }] {
    return apiMock.mock.calls[index] as [string, { method?: string, body?: Record<string, unknown>, responseType?: string }]
  }

  it('changePassword poste l\'ancien et le nouveau mot de passe', async () => {
    apiMock.mockResolvedValueOnce(undefined)
    await useAccount().changePassword('secret-Test-123', 'secret-Test-456')
    const [path, options] = callOf()
    expect(path).toBe('/api/v1/account/password')
    expect(options.body).toEqual({ currentPassword: 'secret-Test-123', newPassword: 'secret-Test-456' })
  })

  it('deleteAccount envoie un DELETE avec le mot de passe', async () => {
    apiMock.mockResolvedValueOnce(undefined)
    await useAccount().deleteAccount('secret-Test-123')
    const [path, options] = callOf()
    expect(path).toBe('/api/v1/account')
    expect(options.method).toBe('DELETE')
    expect(options.body).toEqual({ currentPassword: 'secret-Test-123' })
  })

  it('exportData demande un blob zip', async () => {
    apiMock.mockResolvedValueOnce(new Blob())
    await useAccount().exportData()
    const [path, options] = callOf()
    expect(path).toBe('/api/v1/account/export')
    expect(options.responseType).toBe('blob')
  })

  it('requestPasswordReset / resetPassword ciblent les bons endpoints publics', async () => {
    apiMock.mockResolvedValue(undefined)
    const account = useAccount()
    await account.requestPasswordReset('m@plume.test')
    await account.resetPassword('tok', 'secret-Test-NEW')
    expect(callOf(0)[0]).toBe('/api/v1/account/password/forgot')
    expect(callOf(1)[0]).toBe('/api/v1/account/password/reset')
    expect(callOf(1)[1].body).toEqual({ token: 'tok', newPassword: 'secret-Test-NEW' })
  })

  it('register / verifyEmail / resendVerification (parcours d\'inscription)', async () => {
    apiMock.mockResolvedValue(undefined)
    const account = useAccount()
    await account.register('m@plume.test', 'secret-Test-123', true)
    await account.verifyEmail('tok')
    await account.resendVerification('m@plume.test')
    expect(callOf(0)[0]).toBe('/api/v1/register')
    expect(callOf(0)[1].body).toEqual({ email: 'm@plume.test', password: 'secret-Test-123', acceptTerms: true })
    expect(callOf(1)[0]).toBe('/api/v1/account/verify-email')
    expect(callOf(2)[0]).toBe('/api/v1/account/verify-email/resend')
  })

  it('2FA : status / setup / confirm / disable', async () => {
    apiMock.mockResolvedValue(undefined)
    const account = useAccount()
    await account.twoFactorStatus()
    await account.twoFactorSetup()
    await account.twoFactorConfirm('123456')
    await account.twoFactorDisable('secret-Test-123')
    expect(callOf(0)[0]).toBe('/api/v1/account/2fa')
    expect(callOf(1)[0]).toBe('/api/v1/account/2fa/setup')
    expect(callOf(2)[1].body).toEqual({ code: '123456' })
    expect(callOf(3)[1].body).toEqual({ currentPassword: 'secret-Test-123' })
  })

  it('sessions : liste / révocation unitaire / révocation des autres', async () => {
    apiMock.mockResolvedValue(undefined)
    const account = useAccount()
    await account.sessions()
    await account.revokeSession(42)
    await account.revokeOtherSessions()
    expect(callOf(0)[0]).toBe('/api/v1/token/sessions')
    expect(callOf(1)[0]).toBe('/api/v1/token/sessions/42')
    expect(callOf(1)[1].method).toBe('DELETE')
    expect(callOf(2)[0]).toBe('/api/v1/token/sessions/revoke-others')
  })
})
