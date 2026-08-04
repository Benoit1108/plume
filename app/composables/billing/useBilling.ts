import type { Subscription } from '~/types/domain/billing'

/** Abonnement (V2.2) : état + démarrage de paiement + portail de gestion. */
export function useBilling() {
  const api = useApi()

  return {
    /** GET /billing/subscription — état pour la page Compte + le bandeau lecture seule. */
    subscription: () => api<Subscription>('/api/v1/billing/subscription'),

    /** POST /billing/checkout — renvoie l'URL de paiement (Stripe réel, ou retour app en factice). */
    checkout: (plan: 'monthly' | 'annual') =>
      api<{ url: string }>('/api/v1/billing/checkout', { method: 'POST', body: { plan } }),

    /** POST /billing/portal — renvoie l'URL du portail Stripe (gérer/annuler). */
    portal: () => api<{ url: string }>('/api/v1/billing/portal', { method: 'POST', body: {} }),
  }
}
