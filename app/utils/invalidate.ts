import type { QueryClient, QueryKey } from '@tanstack/vue-query'
import { queryKeys } from './queryKeys'

/**
 * Matrice d'invalidation « une piste a changé » (revue pré-V2) : toute mutation de piste
 * (transition, création, promotion d'un candidat, action rapide « Aujourd'hui ») impacte le
 * kanban, l'écran du jour ET les KPI du tableau de bord — plus la fiche + son journal si connue.
 * Centralisé pour éviter la sous-invalidation croisée relevée par la revue.
 */
export async function invalidateLeadRelated(queryClient: QueryClient, leadId?: string): Promise<void> {
  const keys: QueryKey[] = [queryKeys.leads, queryKeys.today, queryKeys.dashboard]
  if (leadId !== undefined && leadId !== '') {
    keys.push(queryKeys.lead(leadId), queryKeys.leadTimeline(leadId))
  }
  await Promise.all(keys.map(key => queryClient.invalidateQueries({ queryKey: key })))
}
