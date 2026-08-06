import type { AppNotification } from '~/types/domain/notifications'

/** Logique PURE du centre de notifications (testée — l'affichage reste dans le composant). */

/** Cible de navigation d'une notification : reconnexion de boîte → l'ONGLET boîte des Réglages,
 *  fiche piste quand on la connaît, sinon l'accueil. */
export function notificationTarget(notification: Pick<AppNotification, 'type' | 'payload'>): string {
  if (notification.type === 'mailbox_disconnected') return '/settings?tab=mailbox'
  if (notification.type === 'candidate_to_triage') return '/candidates'
  const leadId = notification.payload.leadId
  return typeof leadId === 'string' && leadId !== '' ? `/leads/${leadId}` : '/today'
}

export function unreadCount(notifications: ReadonlyArray<Pick<AppNotification, 'readAt'>>): number {
  return notifications.filter(n => !n.readAt).length
}

/** Libellé du badge : plafonné pour rester lisible (« 9+ »). */
export function unreadBadge(count: number): string {
  return count > 9 ? '9+' : String(count)
}
