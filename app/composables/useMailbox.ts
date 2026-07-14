import type { Mailbox } from '~/types/mailbox'

/** Client de l'API Passerelle email (connexion OAuth, état, révocation). */
export function useMailbox() {
  const api = useApi()
  const ld = { Accept: 'application/ld+json' }
  const ldWrite = { 'Content-Type': 'application/ld+json' }

  return {
    /** Singleton : toujours 200 — `status: 'NONE'` tant que rien n'est connecté. */
    get: () => api<Mailbox>('/api/v1/mailbox', { headers: ld }),
    /** Démarre le consentement : renvoie l'URL où envoyer le navigateur. */
    async startOAuth(): Promise<string> {
      const res = await api<{ authorizationUrl: string }>('/api/v1/mailbox/oauth/start', { method: 'POST', body: {}, headers: ldWrite })
      return res.authorizationUrl
    },
    /** Finalise la connexion au retour du consentement (code + state anti-CSRF). */
    connect: (code: string, state: string) =>
      api<Mailbox>('/api/v1/mailbox/connect', { method: 'POST', body: { code, state }, headers: ldWrite }),
    /** Relève immédiate (le Scheduler le fait toutes les 5 min). */
    fetchReplies: () => api<Mailbox>('/api/v1/mailbox/fetch-replies', { method: 'POST', body: {}, headers: ldWrite }),
    revoke: () => api<unknown>('/api/v1/mailbox', { method: 'DELETE' }),
  }
}
