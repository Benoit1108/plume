/**
 * Met en forme le délai moyen de 1re réponse. L'API le renvoie en JOURS, arrondi au dixième :
 * afficher « {n} j » brut effaçait l'information là où elle est la plus flatteuse — répondre en
 * quatre heures s'affichait « 0 j » (revue P3). Sous un jour, on bascule en heures.
 *
 * `format` reçoit la clé i18n et le nombre déjà arrondi ; la traduction gère le pluriel.
 */
export function formatResponseDelay(
  days: number | null,
  format: (key: 'days' | 'hours' | 'minutes', value: number) => string,
): string {
  if (days === null || Number.isNaN(days) || days < 0) return '—'

  if (days >= 1) return format('days', Math.round(days * 10) / 10)

  const hours = days * 24
  if (hours >= 1) return format('hours', Math.round(hours))

  // Sous l'heure : les minutes. (Le dixième de jour de l'API borne la précision à ~2,4 min.)
  return format('minutes', Math.max(1, Math.round(hours * 60)))
}
