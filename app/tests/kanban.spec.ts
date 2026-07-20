import { describe, expect, it } from 'vitest'
import type { Lead, LeadAction, LeadStatus } from '../types/leads'
import { ACTION_FOR_STATUS, kanbanActionFor } from '../utils/kanban'

/** Fabrique une piste minimale pour la logique DnD (seuls statut + actions comptent). */
function lead(status: LeadStatus, allowedActions: LeadAction[]): Pick<Lead, 'status' | 'allowedActions'> {
  return { status, allowedActions }
}

describe('kanbanActionFor', () => {
  it('renvoie l\'action métier menant à la colonne quand elle est autorisée', () => {
    expect(kanbanActionFor(lead('TO_CONTACT', ['contact']), 'CONTACTED')).toBe('contact')
    expect(kanbanActionFor(lead('IN_DISCUSSION', ['win', 'lose']), 'WON')).toBe('win')
    expect(kanbanActionFor(lead('CONTACTED', ['follow-up']), 'FOLLOWED_UP')).toBe('follow-up')
  })

  it('refuse (null) un déplacement dont l\'action n\'est pas dans allowedActions', () => {
    // La cible a bien une action associée, mais la machine à états ne l'autorise pas ici.
    expect(kanbanActionFor(lead('TO_CONTACT', ['contact']), 'WON')).toBeNull()
    expect(kanbanActionFor(lead('WON', []), 'LOST')).toBeNull()
  })

  it('traite le dépôt dans la propre colonne de la piste comme un no-op', () => {
    expect(kanbanActionFor(lead('CONTACTED', ['follow-up', 'win']), 'CONTACTED')).toBeNull()
  })

  it('n\'associe aucune action à la colonne TO_CONTACT (état initial, pas de transition entrante par DnD)', () => {
    expect(ACTION_FOR_STATUS.TO_CONTACT).toBeUndefined()
    expect(kanbanActionFor(lead('CONTACTED', ['contact']), 'TO_CONTACT')).toBeNull()
  })

  it('exclut resume du DnD : aucune colonne ne mappe vers l\'action resume (cible dynamique)', () => {
    expect(Object.values(ACTION_FOR_STATUS)).not.toContain('resume')
    // Même une piste en pause autorisée à reprendre ne peut pas être glissée : PAUSED->PAUSED est un no-op,
    // et aucune autre colonne ne porte l'action resume.
    expect(kanbanActionFor(lead('PAUSED', ['resume']), 'PAUSED')).toBeNull()
  })
})
