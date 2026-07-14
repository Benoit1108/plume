import type { Dashboard } from '~/types/dashboard'

/**
 * Les calculs du tableau de bord, extraits de la page pour être testés :
 * ratios avec dénominateurs explicites (null = « pas encore de matière »),
 * géométrie des barres hebdo (hauteurs relatives au max actes/objectif).
 */
export function useDashboardMetrics(board: Ref<Dashboard | null>) {
  /** Pistes décidées = gagnées + perdues (dénominateur de la conversion). */
  const decided = computed(() => (board.value ? board.value.won + board.value.lost : 0))

  /** Conversion = gagnées / décidées — null tant que rien n'est décidé. */
  const conversion = computed(() => {
    if (!board.value || decided.value === 0) return null
    return board.value.won / decided.value
  })

  /** Taux de réponse = pistes avec réponse / pistes contactées — null sans contact. */
  const responseRate = computed(() => {
    if (!board.value || board.value.contacted === 0) return null
    return board.value.replied / board.value.contacted
  })

  const totalLeads = computed(() => board.value?.pipeline.reduce((sum, slice) => sum + slice.count, 0) ?? 0)

  /** Échelle des barres : le max des actes OU l'objectif (la ligne doit rester visible). */
  const weeklyMax = computed(() => {
    if (!board.value) return 1
    return Math.max(board.value.weeklyTarget, ...board.value.weeklyActivity.map(week => week.acts), 1)
  })

  /** Hauteur d'une barre en % de la zone (0 acte → 0 %). */
  function barHeightPercent(acts: number): number {
    return Math.round((acts / weeklyMax.value) * 100)
  }

  /** Position verticale de la ligne d'objectif en % de la zone. */
  const goalLinePercent = computed(() => {
    if (!board.value) return 0
    return Math.round((board.value.weeklyTarget / weeklyMax.value) * 100)
  })

  /** Taux de réponse d'un segment — null si aucune piste contactée dedans. */
  function segmentRatio(contacted: number, replied: number): number | null {
    return contacted === 0 ? null : replied / contacted
  }

  return { decided, conversion, responseRate, totalLeads, weeklyMax, barHeightPercent, goalLinePercent, segmentRatio }
}
