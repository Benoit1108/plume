import type { AppNotification } from '~/types/notifications'
import type { JsonLdCollection } from '~/types/api'

/** Centre de notifications : liste (50 dernières) + marquage lu. */
export function useNotifications() {
  const api = useApi()
  const ld = { Accept: 'application/ld+json' }
  const ldWrite = { 'Accept': 'application/ld+json', 'Content-Type': 'application/ld+json' }

  return {
    /** GET /notifications — les plus récentes d'abord (le compteur non-lu se dérive de la liste). */
    async list(): Promise<AppNotification[]> {
      const res = await api<JsonLdCollection<AppNotification>>('/api/v1/notifications', { headers: ld })
      return res.member ?? res['hydra:member'] ?? []
    },

    /** POST /notifications/{id}/read — idempotent. */
    markRead: (id: string) =>
      api<unknown>(`/api/v1/notifications/${id}/read`, { method: 'POST', body: {}, headers: ldWrite }),

    /** POST /notifications/read-all — idempotent. */
    markAllRead: () =>
      api<unknown>('/api/v1/notifications/read-all', { method: 'POST', body: {}, headers: ldWrite }),
  }
}
