import type { Dashboard } from '~/types/dashboard'

/** Client de l'API Tableau de bord (lecture seule). */
export function useDashboard() {
  const api = useApi()

  return {
    get: () => api<Dashboard>('/api/v1/dashboard', { headers: { Accept: 'application/ld+json' } }),
  }
}
