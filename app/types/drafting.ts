import type { Schemas } from './api-schemas'

// Enums dérivés du contrat OpenAPI (durcissement) → drift détecté si le back change.
export type DraftType = Schemas['Draft-draft.read']['type']
export type DraftStatus = Schemas['Draft-draft.read']['status']
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
