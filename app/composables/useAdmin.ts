import type { AdminAccount, AdminOverview } from '~/types/admin'

/** Back-office (ROLE_ADMIN) : vue d'ensemble, comptes, actions support. */
export function useAdmin() {
  const api = useApi()

  return {
    /** GET /admin/overview — comptages cross-tenant (jamais de contenu métier). */
    overview: () => api<AdminOverview>('/api/v1/admin/overview'),

    /** GET /admin/accounts?q= — comptes des traductrices (admins exclus), max 100. */
    async accounts(q = ''): Promise<AdminAccount[]> {
      const res = await api<{ accounts: AdminAccount[] }>('/api/v1/admin/accounts', { query: q ? { q } : {} })
      return res.accounts
    },

    /** POST /admin/accounts/{tenantId}/request-deletion — suppression RGPD côté support (soft-delete). */
    requestDeletion: (tenantId: string) =>
      api<unknown>(`/api/v1/admin/accounts/${tenantId}/request-deletion`, { method: 'POST', body: {} }),

    /** POST /admin/accounts/{tenantId}/reset-2fa — dernier recours : désactive la 2FA (support). */
    resetTwoFactor: (tenantId: string) =>
      api<unknown>(`/api/v1/admin/accounts/${tenantId}/reset-2fa`, { method: 'POST', body: {} }),
  }
}
