/** Erreur HTTP 409 (conflit métier : transition interdite, état déjà réglé…). */
export function isConflict(error: unknown): boolean {
  if (typeof error !== 'object' || error === null) return false
  const candidate = error as { statusCode?: unknown, status?: unknown }
  return candidate.statusCode === 409 || candidate.status === 409
}

/** Titre de toast d'échec : les conflits métier méritent mieux que « une erreur est survenue ». */
export function errorToastTitle(t: (key: string) => string, error: unknown): string {
  return isConflict(error) ? t('common.conflict') : t('common.error')
}

/** Détail lisible renvoyé par l'API (clé `detail` du problem+json), s'il existe. */
export function errorDetail(error: unknown): string | undefined {
  if (typeof error !== 'object' || error === null) return undefined
  const detail = (error as { data?: { detail?: unknown } }).data?.detail
  return typeof detail === 'string' ? detail : undefined
}
