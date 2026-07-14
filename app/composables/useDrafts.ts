import type { Draft, DraftType, Template, TemplateInput } from '~/types/drafting'

interface JsonLdCollection<T> {
  'member'?: T[]
  'hydra:member'?: T[]
}

/** Client de l'API Rédaction assistée (brouillons + gabarits). */
export function useDrafts() {
  const api = useApi()
  const ld = { Accept: 'application/ld+json' }
  const ldWrite = { 'Content-Type': 'application/ld+json' }
  const ldPatch = { 'Content-Type': 'application/merge-patch+json' }

  return {
    async forLead(leadId: string): Promise<Draft[]> {
      const res = await api<JsonLdCollection<Draft>>(`/api/v1/leads/${leadId}/drafts`, { headers: ld })
      return res.member ?? res['hydra:member'] ?? []
    },
    get: (id: string) => api<Draft>(`/api/v1/drafts/${id}`, { headers: ld }),
    /** Génération asynchrone : la réponse peut encore être GENERATING. */
    generate: (leadId: string, data: { type: DraftType, targetLanguage: string, templateId?: string | null }) =>
      api<Draft>(`/api/v1/leads/${leadId}/drafts`, { method: 'POST', body: data, headers: ldWrite }),
    /** Édition (READY uniquement — 409 sinon). */
    edit: (id: string, data: { subject?: string | null, body: string }) =>
      api<Draft>(`/api/v1/drafts/${id}`, { method: 'PATCH', body: data, headers: ldPatch }),
    regenerate: (id: string) =>
      api<Draft>(`/api/v1/drafts/${id}/regenerate`, { method: 'POST', body: {}, headers: ldWrite }),
    remove: (id: string) =>
      api<unknown>(`/api/v1/drafts/${id}`, { method: 'DELETE' }),

    async templates(): Promise<Template[]> {
      const res = await api<JsonLdCollection<Template>>('/api/v1/templates', { headers: ld })
      return res.member ?? res['hydra:member'] ?? []
    },
    createTemplate: (data: TemplateInput) =>
      api<Template>('/api/v1/templates', { method: 'POST', body: data, headers: ldWrite }),
    updateTemplate: (id: string, data: TemplateInput) =>
      api<Template>(`/api/v1/templates/${id}`, { method: 'PATCH', body: data, headers: ldPatch }),
    removeTemplate: (id: string) =>
      api<unknown>(`/api/v1/templates/${id}`, { method: 'DELETE' }),
  }
}
