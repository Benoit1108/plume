/** Back-office (routes /api/v1/admin/*, hors contrat API Platform — outil interne). */

export interface AdminOverview {
  accounts: { total: number, unverified: number, pendingDeletion: number }
  business: {
    organizations: number
    leads: number
    messagesSent: number
    candidatesPending: number
    mailboxesConnected: number
    mailboxesError: number
  }
  /** Profondeur des files Messenger par queue (un `failed` qui grossit = incident). */
  queues: Record<string, number>
}

/** Statut opérationnel interne (distinct de la sonde publique /health). */
export interface AdminStatus {
  db: string
  /** Profondeur EN ATTENTE par queue Messenger. */
  queues: Record<string, number>
  /** Messages dans la file `failed` (à rejouer). */
  failed: number
  /** Âge du plus vieux message en attente (hors failed) — un backlog qui vieillit = worker bloqué. */
  backlogAgeSeconds: number
  mailboxesError: number
  /** Garde-fou coût IA : consommation du mois vs plafond (0 = illimité) + coupe-circuit. */
  aiUsage: {
    enabled: boolean
    monthlyTokenBudget: number
    periodTokens: number
    calls: number
  }
}

/** KPIs produit (comptages/répartitions, sans PII). */
export interface AdminMetrics {
  accounts: { total: number, verified: number, active30d: number }
  /** Inscriptions par semaine (8 dernières), semaines sans inscription omises. */
  signups: Array<{ week: string, count: number }>
  leadsByStatus: Record<string, number>
  totals: { organizations: number, leads: number, messagesSent: number }
}

export interface AdminAccount {
  tenantId: string
  email: string
  emailVerified: boolean
  deletionRequestedAt: string | null
  createdAt: string | null
  organizations: number
  leads: number
  mailboxStatus: string
}

/** Fiche compte détaillée (back-office, support). */
export interface AdminAccountDetail {
  tenantId: string
  email: string
  emailVerified: boolean
  deletionRequestedAt: string | null
  createdAt: string | null
  lastLoginAt: string | null
  twoFactorEnabled: boolean
  digestFrequency: string
  lastActivityAt: string | null
  mailbox: { provider: string, status: string } | null
  organizations: number
  leads: number
  messagesSent: number
}

/** Courbes & entonnoir : croissance dans le temps + acquisition. */
export interface AdminTrends {
  weeklyActive: { week: string, count: number }[]
  funnel: { signedUp: number, verified: number, activated: number, active30d: number }
}

/** Santé & alertes : la liste « à regarder » du back-office. */
export interface AdminAlerts {
  inactiveAccounts: { email: string, lastActivityAt: string | null }[]
  mailboxesInError: { email: string }[]
  stuckVerification: { email: string, createdAt: string | null }[]
}

/** Une entrée du journal d'audit hors tenant (connexions admin, suppressions, resets). */
export interface AdminAuditEntry {
  id: string
  actor: string
  action: string
  target: string
  details: Record<string, unknown>
  occurredAt: string
}
