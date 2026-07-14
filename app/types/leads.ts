export type LeadStatus =
  | 'TO_CONTACT' | 'CONTACTED' | 'FOLLOWED_UP' | 'IN_DISCUSSION'
  | 'SAMPLE_TEST' | 'WON' | 'LOST' | 'PAUSED'

export type LeadPriority = 'LOW' | 'MEDIUM' | 'HIGH'
export type LeadSource = 'DIRECT' | 'REFERRAL' | 'JOB_BOARD' | 'OTHER'
export type LeadAction = 'contact' | 'follow-up' | 'reply' | 'sample-test' | 'win' | 'lose' | 'pause' | 'resume'

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
  createdAt: string
  lastContactedAt?: string | null
  lastReplyAt?: string | null
  nextFollowUpAt?: string | null
  nextFollowUpLabel?: string | null
}

export type LeadInput = Pick<Lead, 'organizationId' | 'contactId' | 'languagePair' | 'source' | 'priority' | 'segment'>

export type InteractionType =
  | 'created' | 'contacted' | 'reply' | 'sample_test' | 'won' | 'lost'
  | 'paused' | 'resumed' | 'note'
  | 'follow_up_scheduled' | 'followed_up' | 'follow_up_cancelled'
  | 'draft_generated'

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
}
