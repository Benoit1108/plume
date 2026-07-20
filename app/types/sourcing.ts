export type CandidateSource = 'PROZ' | 'LINKEDIN' | 'TRANSLATORSCAFE' | 'RSS' | 'MANUAL'
export type CandidateStatus = 'PENDING' | 'ACCEPTED' | 'MERGED' | 'REJECTED'

export interface CandidateLead {
  id: string
  source: CandidateSource
  status: CandidateStatus
  title: string
  organizationName?: string | null
  languagePair?: string | null
  url?: string | null
  excerpt?: string | null
  postedAt?: string | null
  ingestedAt: string
}

export interface CandidateAcceptInput {
  organizationName: string
  organizationType: string
  languagePair: string
  segment: string
  priority: string
  website?: string | null
}

export interface CandidateMergeInput {
  organizationId: string
  languagePair: string
  segment: string
  priority: string
}

/** Flux d'annonces configuré (M3.1b). */
export interface AlertFeed {
  id: string
  source: CandidateSource
  url: string
  label: string
  active: boolean
  createdAt: string
}

export interface AlertFeedInput {
  source: CandidateSource
  url: string
  label?: string | null
}
