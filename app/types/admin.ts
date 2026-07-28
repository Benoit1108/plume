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

export interface AdminAccount {
  tenantId: string
  email: string
  emailVerified: boolean
  deletionRequestedAt: string | null
  organizations: number
  leads: number
  mailboxStatus: string
}
