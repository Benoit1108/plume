export type MailboxStatus = 'NONE' | 'CONNECTED' | 'ERROR' | 'REVOKED'

/** La boîte email connectée du tenant (une seule en V1) — jamais de token ici. */
export interface Mailbox {
  provider: 'GMAIL' | 'OUTLOOK'
  emailAddress: string
  status: MailboxStatus
  /** Code de raison affichable (i18n : mailbox.failures.*). */
  failureReason?: string | null
  connectedAt?: string | null
  lastSyncAt?: string | null
}
