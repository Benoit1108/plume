import { beforeEach, describe, expect, it, vi } from 'vitest'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useMailbox } = await import('../composables/useMailbox')

describe('useMailbox', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('get lit la ressource singleton (statut NONE quand rien n\'est connecté)', async () => {
    apiMock.mockResolvedValueOnce({ status: 'NONE', emailAddress: '' })
    await expect(useMailbox().get()).resolves.toMatchObject({ status: 'NONE' })

    apiMock.mockResolvedValueOnce({ provider: 'GMAIL', status: 'CONNECTED', emailAddress: 'm@gmail.example' })
    await expect(useMailbox().get()).resolves.toMatchObject({ status: 'CONNECTED' })
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/mailbox')
  })

  it('startOAuth renvoie l\'URL de consentement', async () => {
    apiMock.mockResolvedValueOnce({ authorizationUrl: 'https://accounts.google.example/auth?state=s' })

    await expect(useMailbox().startOAuth()).resolves.toContain('state=s')
    const [path, options] = apiMock.mock.calls[0] as [string, { method: string }]
    expect(path).toBe('/api/v1/mailbox/oauth/start')
    expect(options.method).toBe('POST')
  })

  it('fetchReplies déclenche la relève immédiate', async () => {
    apiMock.mockResolvedValueOnce({ status: 'CONNECTED' })

    await useMailbox().fetchReplies()

    const [path, options] = apiMock.mock.calls[0] as [string, { method: string }]
    expect(path).toBe('/api/v1/mailbox/fetch-replies')
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
