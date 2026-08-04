/** État d'abonnement (V2.2) pour l'UI. `none` = aucun abonnement (compte grandfathered). */
export type SubscriptionStatus = 'trialing' | 'active' | 'past_due' | 'canceled' | 'comped' | 'none'

export interface Subscription {
  status: SubscriptionStatus
  /** Fin de l'essai (ISO) — présent seulement en `trialing`. */
  trialEndsAt: string | null
  /** Le compte peut-il écrire (produit) ? Faux ⇒ lecture seule. */
  entitled: boolean
  /** Un portail Stripe est ouvrable (le compte a déjà un client Stripe). */
  canManage: boolean
}
