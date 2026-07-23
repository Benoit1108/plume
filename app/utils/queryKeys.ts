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
  organizations: ['organizations'] as const,
  leads: ['leads'] as const,
  organization: (id: string) => ['organization', id] as const,
  lead: (id: string) => ['lead', id] as const,
  leadTimeline: (id: string) => ['lead', id, 'timeline'] as const,
  draftsForLead: (leadId: string) => ['drafts', leadId] as const,
} as const
