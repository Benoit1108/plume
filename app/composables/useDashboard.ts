import type { Dashboard, DashboardPeriod } from '~/types/dashboard'

/** Client de l'API Tableau de bord (lecture seule). */
export function useDashboard() {
  const api = useApi()

  // La période ne part en query que si elle restreint (all = défaut serveur, URL propre).
  const periodQuery = (period?: DashboardPeriod) =>
    period && period !== 'all' ? { period } : {}

  return {
    get: (period?: DashboardPeriod) =>
      api<Dashboard>('/api/v1/dashboard', { headers: { Accept: 'application/ld+json' }, query: periodQuery(period) }),
    exportCsv: (period?: DashboardPeriod) =>
      api<Blob>('/api/v1/dashboard/export', { responseType: 'blob', query: periodQuery(period) }),
  }
}
