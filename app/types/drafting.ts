export type DraftType = 'APPLICATION_EMAIL' | 'COVER_LETTER' | 'FOLLOW_UP_EMAIL'
export type DraftStatus = 'GENERATING' | 'READY' | 'FAILED'
export type Segment = 'PUBLISHING' | 'AUDIOVISUAL' | 'TECHNICAL' | 'OTHER'

/** Brouillon généré (draft-first : relu, édité, copié — jamais envoyé en M1). */
export interface Draft {
  id: string
  leadId: string
  type: DraftType
  targetLanguage: string
  templateId?: string | null
  subject?: string | null
  body: string
  status: DraftStatus
  /** Code de raison affichable (i18n : drafts.failures.*). */
  failureReason?: string | null
  createdAt: string
  updatedAt: string
}

/** Gabarit de message ({{contact}}, {{organisation}}, {{langues}}, {{bio}}, {{specialites}}, {{signature}}). */
export interface Template {
  id: string
  name: string
  type: DraftType
  segment: Segment
  language: string
  subject?: string | null
  body: string
}

export type TemplateInput = Omit<Template, 'id'>
