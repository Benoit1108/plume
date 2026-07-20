import type { Lead, LeadAction, LeadStatus } from '~/types/leads'

/**
 * Statut cible d'une colonne kanban -> action métier qui y mène.
 * `resume` est exclu : sa cible est dynamique (dépend du statut d'avant la pause),
 * donc un glisser-déposer ne peut pas la déduire de la seule colonne d'arrivée.
 */
export const ACTION_FOR_STATUS: Partial<Record<LeadStatus, LeadAction>> = {
  CONTACTED: 'contact',
  FOLLOWED_UP: 'follow-up',
  IN_DISCUSSION: 'reply',
  SAMPLE_TEST: 'sample-test',
  WON: 'win',
  LOST: 'lose',
  PAUSED: 'pause',
}

/**
 * L'action qui fait passer `lead` dans la colonne `targetStatus`, ou `null` si le
 * déplacement est illégal. Une transition n'est autorisée que si elle figure dans
 * `allowedActions` (source de vérité = la machine à états du domaine, exposée par l'API).
 * Déposer une carte dans sa propre colonne est un no-op (null).
 */
export function kanbanActionFor(
  lead: Pick<Lead, 'status' | 'allowedActions'>,
  targetStatus: LeadStatus,
): LeadAction | null {
  if (lead.status === targetStatus) return null
  const action = ACTION_FOR_STATUS[targetStatus]
  return action && lead.allowedActions.includes(action) ? action : null
}
