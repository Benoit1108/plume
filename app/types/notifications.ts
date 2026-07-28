import type { Schemas } from './api-schemas'

// Type stable dérivé du contrat OpenAPI (drift détecté si le back change).
export type NotificationType = Schemas['Notification-notification.read']['type']

/** Une notification du centre (projection sur les domain events + échéances). */
export interface AppNotification {
  id: string
  type: NotificationType
  /** Données propres au type : leadId, preview (réponse), reason (échec), orgName/label (relance). */
  payload: Record<string, unknown>
  readAt?: string | null
  occurredOn: string
}
