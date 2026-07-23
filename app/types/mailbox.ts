import type { Schemas } from './api-schemas'

// Enum dérivé du contrat OpenAPI (durcissement) → drift détecté si le back change.
export type MailboxStatus = Schemas['Mailbox-mailbox.read']['status']

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
