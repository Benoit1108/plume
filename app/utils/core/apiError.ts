/** Code HTTP porté par une erreur $fetch (formes `status` / `statusCode` / `response.status`). */
export function errorStatus(error: unknown): number | undefined {
  if (typeof error !== 'object' || error === null) return undefined
  const candidate = error as { statusCode?: unknown, status?: unknown, response?: { status?: unknown } }
  for (const value of [candidate.statusCode, candidate.status, candidate.response?.status]) {
    if (typeof value === 'number') return value
  }
  return undefined
}

/** Erreur HTTP 409 (conflit métier : transition interdite, état déjà réglé…). */
export function isConflict(error: unknown): boolean {
  return errorStatus(error) === 409
}

/**
 * Titre de toast d'échec. « Une erreur est survenue » est un aveu d'ignorance : quand le serveur
 * DIT pourquoi il refuse, on le traduit (revue UX-P2b). Chaque cas ici a une suite d'action claire :
 * s'abonner, attendre, sortir de la démo, corriger la saisie.
 */
export function errorToastTitle(t: (key: string) => string, error: unknown): string {
  const status = errorStatus(error)
  const detail = errorDetail(error)

  if (status === 402) return t('errors.subscriptionRequired')
  if (status === 429) return t('errors.tooManyRequests')
  if (status === 403 && detail === 'demo_restricted') return t('errors.demoRestricted')
  if (status === 409) return t('common.conflict')
  // 422 : l'API renvoie un détail exploitable (contrainte violée) — le montrer vaut mieux qu'un
  // message générique, mais on ne l'affiche QUE s'il existe.
  if (status === 422 && detail !== undefined && detail !== '') return detail

  return t('common.error')
}

/** Détail lisible renvoyé par l'API (clé `detail` du problem+json), s'il existe. */
export function errorDetail(error: unknown): string | undefined {
  if (typeof error !== 'object' || error === null) return undefined
  const detail = (error as { data?: { detail?: unknown } }).data?.detail
  return typeof detail === 'string' ? detail : undefined
}
