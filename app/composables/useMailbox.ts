import type { Mailbox } from '~/types/mailbox'

/** Client de l'API Passerelle email (connexion OAuth, état, révocation). */
export function useMailbox() {
  const api = useApi()
  const ld = { Accept: 'application/ld+json' }
  const ldWrite = { 'Content-Type': 'application/ld+json' }

  return {
    /** null = aucune boîte connectée (404 de l'API). */
    async get(): Promise<Mailbox | null> {
      try {
        return await api<Mailbox>('/api/v1/mailbox', { headers: ld })
      }
      catch (error) {
        const status = (error as { response?: { status?: number } })?.response?.status
        if (status === 404) return null
        throw error
      }
    },
    /** Démarre le consentement : renvoie l'URL où envoyer le navigateur. */
    async startOAuth(): Promise<string> {
      const res = await api<{ authorizationUrl: string }>('/api/v1/mailbox/oauth/start', { method: 'POST', body: {}, headers: ldWrite })
      return res.authorizationUrl
    },
    /** Finalise la connexion au retour du consentement (code + state anti-CSRF). */
    connect: (code: string, state: string) =>
      api<Mailbox>('/api/v1/mailbox/connect', { method: 'POST', body: { code, state }, headers: ldWrite }),
    revoke: () => api<unknown>('/api/v1/mailbox', { method: 'DELETE' }),
  }
}
