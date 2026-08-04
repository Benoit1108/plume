import type { JsonLdCollection } from '~/types/api'
import type { AlertFeed, AlertFeedInput, CandidateAcceptInput, CandidateLead, CandidateMergeInput } from '~/types/sourcing'

/** Client de la file de tri (Sourcing, M3). `pendingCount` est partagé (badge de nav). */
export function useSourcing() {
  const api = useApi()
  const ld = { Accept: 'application/ld+json' }
  const ldWrite = { 'Content-Type': 'application/ld+json' }

  const pendingCount = useState<number>('sourcing-pending', () => 0)

  async function queue(): Promise<CandidateLead[]> {
    const res = await api<JsonLdCollection<CandidateLead>>('/api/v1/candidate-leads', { headers: ld })
    const items = res.member ?? res['hydra:member'] ?? []
    pendingCount.value = items.length
    return items
  }

  /** Rafraîchit le compteur du badge (best-effort, silencieux). */
  async function refreshCount(): Promise<void> {
    try {
      await queue()
    }
    catch {
      /* badge best-effort : on n'affiche pas d'erreur pour un compteur. */
    }
  }

  return {
    queue,
    refreshCount,
    pendingCount,
    accept: (id: string, body: CandidateAcceptInput) =>
      api<unknown>(`/api/v1/candidate-leads/${id}/accept`, { method: 'POST', body, headers: ldWrite }),
    merge: (id: string, body: CandidateMergeInput) =>
      api<unknown>(`/api/v1/candidate-leads/${id}/merge`, { method: 'POST', body, headers: ldWrite }),
    reject: (id: string) =>
      api<unknown>(`/api/v1/candidate-leads/${id}/reject`, { method: 'POST', body: {}, headers: ldWrite }),
    /** Relève les sources configurées (tenant courant) et ingère les annonces trouvées. */
    poll: () =>
      api<unknown>('/api/v1/sources/poll', { method: 'POST', body: {}, headers: ldWrite }),

    /** Flux d'annonces configurés (M3.1b). */
    async feeds(): Promise<AlertFeed[]> {
      const res = await api<JsonLdCollection<AlertFeed>>('/api/v1/sources', { headers: ld })
      return res.member ?? res['hydra:member'] ?? []
    },
    addFeed: (body: AlertFeedInput) =>
      api<unknown>('/api/v1/sources', { method: 'POST', body, headers: ldWrite }),
    setFeedActive: (id: string, active: boolean) =>
      api<unknown>(`/api/v1/sources/${id}/${active ? 'activate' : 'deactivate'}`, { method: 'POST', body: {}, headers: ldWrite }),
    removeFeed: (id: string) =>
      api<unknown>(`/api/v1/sources/${id}`, { method: 'DELETE' }),
  }
}
