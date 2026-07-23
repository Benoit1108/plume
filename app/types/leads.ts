import type { Schemas } from './api-schemas'

// Enums dérivés du contrat OpenAPI (durcissement) → drift détecté si le back change.
export type LeadStatus = Schemas['Lead-lead.read']['status']
export type LeadPriority = Schemas['Lead-lead.read']['priority']
export type LeadSource = Schemas['Lead-lead.read']['source']
// Hors contrat (allowedActions exposé en string[]) → maintenu à la main.
export type LeadAction = 'contact' | 'back-to-contact' | 'follow-up' | 'reply' | 'sample-test' | 'win' | 'lose' | 'pause' | 'resume'

/** Piste telle que retournée par l'API (persistée : id garanti). */
export interface Lead {
  id: string
  organizationId: string
  organizationName: string | null
  contactId?: string | null
  languagePair: string
  source: LeadSource
  priority: LeadPriority
  segment: string
  status: LeadStatus
  allowedActions: LeadAction[]
  hasReachableContact: boolean
  createdAt: string
  lastContactedAt?: string | null
  lastReplyAt?: string | null
  nextFollowUpAt?: string | null
  nextFollowUpLabel?: string | null
}

export type LeadInput = Pick<Lead, 'organizationId' | 'contactId' | 'languagePair' | 'source' | 'priority' | 'segment'>

export type InteractionType =
  | 'created' | 'contacted' | 'back_to_contact' | 'reply' | 'sample_test' | 'won' | 'lost'
  | 'paused' | 'resumed' | 'note'
  | 'follow_up_scheduled' | 'followed_up' | 'follow_up_cancelled'
  | 'draft_generated'
  | 'email_sent' | 'email_send_failed'

export interface Interaction {
  id: string
  type: InteractionType
  payload: Record<string, unknown>
  occurredOn: string
}

export interface Today {
  followUpsDue: Lead[]
  toContact: Lead[]
  weeklyTarget: number
  weeklyDone: number
  streak: number
}

export interface Profile {
  weeklyGoal: number
  timezone: string
  bio?: string | null
  specialties?: string | null
  signature?: string | null
  firstName?: string | null
  lastName?: string | null
}
