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
  pipeline: { status: LeadStatus, count: number }[]
  weeklyActivity: { weekStart: string, acts: number }[]
  segments: { segment: string, contacted: number, replied: number, won: number }[]
}
