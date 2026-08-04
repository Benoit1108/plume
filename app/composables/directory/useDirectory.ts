import type { Contact, ImportResult, Organization } from '~/types/directory'
import type { JsonLdCollection } from '~/types/api'

/** Client de l'API Répertoire (via useApi : Bearer + proxy /api en dev). */
export function useDirectory() {
  const api = useApi()
  const ld = { Accept: 'application/ld+json' }
  const ldWrite = { 'Content-Type': 'application/ld+json' }
  const patch = { 'Content-Type': 'application/merge-patch+json' }

  return {
    async list(params: { type?: string, q?: string } = {}): Promise<Organization[]> {
      const res = await api<JsonLdCollection<Organization>>('/api/v1/organizations', { query: params, headers: ld })
      return res.member ?? res['hydra:member'] ?? []
    },
    get: (id: string) => api<Organization>(`/api/v1/organizations/${id}`, { headers: ld }),
    create: (data: Partial<Organization>) =>
      api<Organization>('/api/v1/organizations', { method: 'POST', body: data, headers: ldWrite }),
    update: (id: string, data: Partial<Organization>) =>
      api<Organization>(`/api/v1/organizations/${id}`, { method: 'PATCH', body: data, headers: patch }),
    addContact: (orgId: string, data: Partial<Contact>) =>
      api<Contact>(`/api/v1/organizations/${orgId}/contacts`, { method: 'POST', body: data, headers: ldWrite }),
    updateContact: (orgId: string, contactId: string, data: Partial<Contact>) =>
      api<Contact>(`/api/v1/organizations/${orgId}/contacts/${contactId}`, { method: 'PATCH', body: data, headers: patch }),
    removeContact: (orgId: string, contactId: string) =>
      api<unknown>(`/api/v1/organizations/${orgId}/contacts/${contactId}`, { method: 'DELETE' }),
    importCsv: (content: string) =>
      api<ImportResult>('/api/v1/organizations/import', { method: 'POST', body: { content }, headers: ldWrite }),
  }
}
