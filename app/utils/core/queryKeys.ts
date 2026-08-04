/**
 * Clés de cache TanStack Query, centralisées (chantier 3, lot D) : une source unique pour que
 * les mutations d'une page invalident les requêtes d'une autre sans risque de faute de frappe.
 */
export const queryKeys = {
  dashboard: ['dashboard'] as const,
  today: ['today'] as const,
  profile: ['profile'] as const,
  mailbox: ['mailbox'] as const,
  feeds: ['feeds'] as const,
  templates: ['templates'] as const,
  candidateQueue: ['candidate-queue'] as const,
  notifications: ['notifications'] as const,
  twoFactor: ['two-factor'] as const,
  sessions: ['sessions'] as const,
  adminOverview: ['admin', 'overview'] as const,
  adminStatus: ['admin', 'status'] as const,
  adminMetrics: ['admin', 'metrics'] as const,
  adminAccounts: ['admin', 'accounts'] as const,
  adminAudit: ['admin', 'audit'] as const,
  adminAlerts: ['admin', 'alerts'] as const,
  adminTrends: ['admin', 'trends'] as const,
  adminBilling: ['admin', 'billing'] as const,
  adminAccount: (tenantId: string) => ['admin', 'account', tenantId] as const,
  billingSubscription: ['billing', 'subscription'] as const,
  organizations: ['organizations'] as const,
  directoryCatalog: ['directory', 'catalog'] as const,
  leads: ['leads'] as const,
  organization: (id: string) => ['organization', id] as const,
  lead: (id: string) => ['lead', id] as const,
  leadTimeline: (id: string) => ['lead', id, 'timeline'] as const,
  draftsForLead: (leadId: string) => ['drafts', leadId] as const,
} as const
