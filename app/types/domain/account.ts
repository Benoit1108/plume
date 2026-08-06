/**
 * Session active (un refresh token côté API). L'appareil et la dernière activité sont `null` pour
 * les sessions ouvertes avant leur introduction (lot « densité »).
 */
export interface Session {
  id: number
  /** Navigateur résumé côté API (« Firefox », « Safari »…). */
  browser: string | null
  /** Plateforme résumée côté API (« Linux », « iPhone »…). */
  platform: string | null
  /** Dernière activité (ISO) — la rotation du token la remet à jour à chaque rafraîchissement. */
  lastSeenAt: string | null
  expiresAt: string | null
  /** Session d'où provient la requête : jamais révocable depuis la liste. */
  current: boolean
}
