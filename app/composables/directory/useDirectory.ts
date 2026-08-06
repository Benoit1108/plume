import type { Contact, ImportResult, Organization } from '~/types/domain/directory'
import type { JsonLdCollection } from '~/types/domain/api'

/** Page de résultats du répertoire : le total sert à numéroter la pagination. */
export interface OrganizationPage {
  items: Organization[]
  total: number
}

/** Taille de page côté API quand on ne pagine pas explicitement (plafond de l'API : 100). */
const UNPAGINATED_ITEMS = 100

/** Client de l'API Répertoire (via useApi : Bearer + proxy /api en dev). */
export function useDirectory() {
  const api = useApi()
  const ld = { Accept: 'application/ld+json' }
  const ldWrite = { 'Content-Type': 'application/ld+json' }
  const patch = { 'Content-Type': 'application/merge-patch+json' }

  return {
    /** Page numérotée (écran Répertoire) : items + total pour la pagination. */
    async page(params: { type?: string, q?: string, page?: number, itemsPerPage?: number } = {}): Promise<OrganizationPage> {
      const res = await api<JsonLdCollection<Organization>>('/api/v1/organizations', { query: params, headers: ld })
      return {
        items: res.member ?? res['hydra:member'] ?? [],
        total: res.totalItems ?? res['hydra:totalItems'] ?? 0,
      }
    },

    /**
     * Liste « à plat » pour les listes de choix (nouvelle piste, fusion au tri, onboarding).
     * Bornée à la taille de page max de l'API — au-delà, il faut chercher (champ de recherche).
     */
    async list(params: { type?: string, q?: string } = {}): Promise<Organization[]> {
      const res = await api<JsonLdCollection<Organization>>('/api/v1/organizations', {
        query: { ...params, itemsPerPage: UNPAGINATED_ITEMS },
        headers: ld,
      })
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
