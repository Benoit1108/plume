import type { LeadStatus } from '~/types/leads'

/** Le tableau de bord — numérateurs/dénominateurs en clair (l'UI affiche « 4 / 12 »). */
export interface Dashboard {
  contacted: number
  replied: number
  won: number
  lost: number
  activeLeads: number
  outreachThisMonth: number
  weeklyTarget: number
  /** Délai moyen (jours) entre 1er contact et 1re réponse ; null si aucune réponse encore. */
  firstResponseDelayDays: number | null
  pipeline: { status: LeadStatus, count: number }[]
  weeklyActivity: { weekStart: string, acts: number }[]
  segments: { segment: string, contacted: number, replied: number, won: number }[]
}
