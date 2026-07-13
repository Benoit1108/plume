import type { Interaction, Lead, LeadAction, LeadInput } from '~/types/leads'

interface JsonLdCollection<T> {
  'member'?: T[]
  'hydra:member'?: T[]
}

/** Client de l'API Pipeline (via useApi : Bearer + proxy /api en dev). */
export function useLeads() {
  const api = useApi()
  const ld = { Accept: 'application/ld+json' }
  const ldWrite = { 'Content-Type': 'application/ld+json' }

  return {
    async list(params: { status?: string, priority?: string, segment?: string } = {}): Promise<Lead[]> {
      const res = await api<JsonLdCollection<Lead>>('/api/v1/leads', { query: { itemsPerPage: '200', ...params }, headers: ld })
      return res.member ?? res['hydra:member'] ?? []
    },
    get: (id: string) => api<Lead>(`/api/v1/leads/${id}`, { headers: ld }),
    create: (data: LeadInput) =>
      api<Lead>('/api/v1/leads', { method: 'POST', body: data, headers: ldWrite }),
    /** Transition métier : POST /leads/{id}/{action} — 409 si interdite. */
    transition: (id: string, action: LeadAction) =>
      api<Lead>(`/api/v1/leads/${id}/${action}`, { method: 'POST', body: {}, headers: ldWrite }),
    addNote: (id: string, text: string) =>
      api<{ text: string }>(`/api/v1/leads/${id}/notes`, { method: 'POST', body: { text }, headers: ldWrite }),
    async timeline(id: string): Promise<Interaction[]> {
      const res = await api<JsonLdCollection<Interaction>>(`/api/v1/leads/${id}/interactions`, { headers: ld })
      return res.member ?? res['hydra:member'] ?? []
    },
  }
}
