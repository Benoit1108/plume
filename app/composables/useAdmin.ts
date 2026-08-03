import type { AdminAccount, AdminAuditEntry, AdminMetrics, AdminOverview, AdminStatus } from '~/types/admin'

/** Back-office (ROLE_ADMIN) : vue d'ensemble, comptes, audit, actions support. */
export function useAdmin() {
  const api = useApi()

  return {
    /** GET /admin/overview — comptages cross-tenant (jamais de contenu métier). */
    overview: () => api<AdminOverview>('/api/v1/admin/overview'),

    /** GET /admin/status — état opérationnel (files, backlog, boîtes en erreur, conso IA). */
    status: () => api<AdminStatus>('/api/v1/admin/status'),

    /** GET /admin/metrics — KPIs produit (comptages/répartitions, sans PII). */
    metrics: () => api<AdminMetrics>('/api/v1/admin/metrics'),

    /** GET /admin/accounts — comptes des traductrices (admins exclus) : recherche + filtre + tri, max 100. */
    async accounts(q = '', status = 'all', sort = 'email'): Promise<AdminAccount[]> {
      const query: Record<string, string> = { status, sort }
      if (q) query.q = q
      const res = await api<{ accounts: AdminAccount[] }>('/api/v1/admin/accounts', { query })
      return res.accounts
    },

    /** GET /admin/accounts/export — mêmes filtres, en CSV (blob à télécharger). */
    accountsExport: (q = '', status = 'all', sort = 'email') =>
      api<Blob>('/api/v1/admin/accounts/export', { responseType: 'blob', query: { ...(q ? { q } : {}), status, sort } }),

    /** GET /admin/audit — journal d'audit hors tenant (filtre optionnel par action), max 200. */
    async audit(action = ''): Promise<AdminAuditEntry[]> {
      const res = await api<{ entries: AdminAuditEntry[] }>('/api/v1/admin/audit', { query: action ? { action } : {} })
      return res.entries
    },

    /** POST /admin/accounts/{tenantId}/request-deletion — suppression RGPD côté support (soft-delete). */
    requestDeletion: (tenantId: string) =>
      api<unknown>(`/api/v1/admin/accounts/${tenantId}/request-deletion`, { method: 'POST', body: {} }),

    /** POST /admin/accounts/{tenantId}/reset-2fa — dernier recours : désactive la 2FA (support). */
    resetTwoFactor: (tenantId: string) =>
      api<unknown>(`/api/v1/admin/accounts/${tenantId}/reset-2fa`, { method: 'POST', body: {} }),
  }
}
