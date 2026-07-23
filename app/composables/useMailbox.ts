import type { Mailbox } from '~/types/mailbox'

/** Client de l'API Passerelle email (connexion OAuth, état, révocation). */
export function useMailbox() {
  const api = useApi()
  const ld = { Accept: 'application/ld+json' }
  const ldWrite = { 'Content-Type': 'application/ld+json' }

  return {
    /** Singleton : toujours 200 — `status: 'NONE'` tant que rien n'est connecté. */
    get: () => api<Mailbox>('/api/v1/mailbox', { headers: ld }),
    /** Démarre le consentement (Gmail ou Outlook) : renvoie l'URL du fournisseur. */
    async startOAuth(provider: 'GMAIL' | 'OUTLOOK'): Promise<string> {
      const res = await api<{ authorizationUrl: string }>('/api/v1/mailbox/oauth/start', { method: 'POST', body: { provider }, headers: ldWrite })
      return res.authorizationUrl
    },
    /** Finalise la connexion au retour du consentement (code + state anti-CSRF). */
    connect: (code: string, state: string) =>
      api<Mailbox>('/api/v1/mailbox/connect', { method: 'POST', body: { code, state }, headers: ldWrite }),
    /** Relève immédiate des réponses (le Scheduler le fait toutes les 5 min). */
    fetchReplies: () => api<Mailbox>('/api/v1/mailbox/fetch-replies', { method: 'POST', body: {}, headers: ldWrite }),
    /** Relève immédiate des alertes du label dédié (le Scheduler le fait toutes les 30 min). */
    fetchAlerts: () => api<Mailbox>('/api/v1/mailbox/fetch-alerts', { method: 'POST', body: {}, headers: ldWrite }),
    revoke: () => api<unknown>('/api/v1/mailbox', { method: 'DELETE' }),
  }
}
