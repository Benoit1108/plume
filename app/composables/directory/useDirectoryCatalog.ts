import type { CatalogEntry } from '~/types/directory'

/** Annuaire suggéré : catalogue de cibles de référence + ajout au Répertoire (endpoint interne). */
export function useDirectoryCatalog() {
  const api = useApi()

  return {
    async list(q = ''): Promise<CatalogEntry[]> {
      const res = await api<{ entries: CatalogEntry[] }>('/api/v1/directory/catalog', { query: q ? { q } : {} })
      return res.entries
    },
    /** Ajoute l'entrée au Répertoire du tenant ; 409 si déjà présente (nom pris). */
    add: (id: string) =>
      api<{ name: string }>('/api/v1/directory/catalog/import', { method: 'POST', body: { id } }),
  }
}
