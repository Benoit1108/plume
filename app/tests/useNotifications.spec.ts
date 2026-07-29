import { beforeEach, describe, expect, it, vi } from 'vitest'
import { notificationTarget, unreadBadge, unreadCount } from '../utils/notifications'

const apiMock = vi.fn()
vi.stubGlobal('useApi', () => apiMock)

const { useNotifications } = await import('../composables/useNotifications')

describe('useNotifications', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('list normalise la collection JSON-LD (member ou hydra:member)', async () => {
    apiMock.mockResolvedValueOnce({ 'hydra:member': [{ id: 'n1', type: 'reply_received', payload: {}, occurredOn: '2026-07-28' }] })
    await expect(useNotifications().list()).resolves.toHaveLength(1)

    apiMock.mockResolvedValueOnce({})
    await expect(useNotifications().list()).resolves.toEqual([])
    expect((apiMock.mock.calls[0] as [string])[0]).toBe('/api/v1/notifications')
  })

  it('markRead / markAllRead postent sur les bons endpoints', async () => {
    apiMock.mockResolvedValue({})
    const notifications = useNotifications()

    await notifications.markRead('n1')
    await notifications.markAllRead()

    const [pathOne, optionsOne] = apiMock.mock.calls[0] as [string, { method: string }]
    const [pathAll, optionsAll] = apiMock.mock.calls[1] as [string, { method: string }]
    expect(pathOne).toBe('/api/v1/notifications/n1/read')
    expect(optionsOne.method).toBe('POST')
    expect(pathAll).toBe('/api/v1/notifications/read-all')
    expect(optionsAll.method).toBe('POST')
  })
})

describe('notificationTarget', () => {
  it('mène à la fiche piste quand le payload la connaît, sinon à l\'accueil', () => {
    expect(notificationTarget({ type: 'reply_received', payload: { leadId: 'L42' } })).toBe('/leads/L42')
    expect(notificationTarget({ type: 'followup_due', payload: {} })).toBe('/today')
    expect(notificationTarget({ type: 'reply_received', payload: { leadId: '' } })).toBe('/today')
    expect(notificationTarget({ type: 'reply_received', payload: { leadId: 12 } })).toBe('/today')
  })

  it('mène aux Réglages pour une reconnexion de boîte (peu importe le payload)', () => {
    expect(notificationTarget({ type: 'mailbox_disconnected', payload: { leadId: 'L42' } })).toBe('/settings')
    expect(notificationTarget({ type: 'mailbox_disconnected', payload: {} })).toBe('/settings')
  })
})

describe('unreadCount / unreadBadge', () => {
  it('compte les non-lues et plafonne le badge à 9+', () => {
    expect(unreadCount([{ readAt: null }, { readAt: '2026-07-28' }, { readAt: undefined }])).toBe(2)
    expect(unreadBadge(3)).toBe('3')
    expect(unreadBadge(12)).toBe('9+')
  })
})
