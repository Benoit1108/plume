import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useMailbox } = await import('../composables/useMailbox')

function http404(): Error {
  return Object.assign(new Error('404'), { response: { status: 404 } })
}

describe('useMailbox', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('get : null quand aucune boîte (404), la boîte sinon, les autres erreurs remontent', async () => {
    apiMock.mockRejectedValueOnce(http404())
    await expect(useMailbox().get()).resolves.toBeNull()

    apiMock.mockResolvedValueOnce({ provider: 'GMAIL', status: 'CONNECTED', emailAddress: 'm@gmail.example' })
    await expect(useMailbox().get()).resolves.toMatchObject({ status: 'CONNECTED' })

    apiMock.mockRejectedValueOnce(Object.assign(new Error('500'), { response: { status: 500 } }))
    await expect(useMailbox().get()).rejects.toThrow('500')
  })

  it('startOAuth renvoie l\'URL de consentement', async () => {
    apiMock.mockResolvedValueOnce({ authorizationUrl: 'https://accounts.google.example/auth?state=s' })

    await expect(useMailbox().startOAuth()).resolves.toContain('state=s')
    const [path, options] = apiMock.mock.calls[0] as [string, { method: string }]
    expect(path).toBe('/api/v1/mailbox/oauth/start')
    expect(options.method).toBe('POST')
  })

  it('connect poste code + state, revoke supprime', async () => {
    apiMock.mockResolvedValue({ status: 'CONNECTED' })
    const mailbox = useMailbox()

    await mailbox.connect('le-code', 'le-state')
    const [connectPath, connectOptions] = apiMock.mock.calls[0] as [string, { body: { code: string, state: string } }]
    expect(connectPath).toBe('/api/v1/mailbox/connect')
    expect(connectOptions.body).toEqual({ code: 'le-code', state: 'le-state' })

    await mailbox.revoke()
    const [revokePath, revokeOptions] = apiMock.mock.calls[1] as [string, { method: string }]
    expect(revokePath).toBe('/api/v1/mailbox')
    expect(revokeOptions.method).toBe('DELETE')
  })
})
