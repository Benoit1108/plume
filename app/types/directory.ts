import type { Schemas } from './api-schemas'

// Enum dérivé du contrat OpenAPI (durcissement) → drift détecté si le back change.
export type OrganizationType = Schemas['Organization-org.read']['type']
export type Segment = 'PUBLISHING' | 'AUDIOVISUAL' | 'TECHNICAL' | 'OTHER'

/** Contact tel que retourné par l'API (persisté : id garanti). */
export interface Contact {
  id: string
  fullName: string
  role?: string | null
  email?: string | null
  phone?: string | null
  linkedinUrl?: string | null
  preferredLanguage?: string | null
  doNotContact: boolean
}

/** Organisation telle que retournée par l'API (persistée : id garanti). */
export interface Organization {
  id: string
  name: string
  type: OrganizationType
  website?: string | null
  country?: string | null
  workingLanguages: string[]
  segments: string[]
  notes?: string | null
  doNotContact: boolean
  contacts: Contact[]
}

export type OrganizationInput = Pick<
  Organization,
  'name' | 'type' | 'website' | 'country' | 'workingLanguages' | 'segments' | 'notes'
>

export type ContactInput = Pick<
  Contact,
  'fullName' | 'role' | 'email' | 'phone' | 'linkedinUrl' | 'preferredLanguage'
>

export interface ImportResult {
  imported: number
  skipped: number
  failed: number
  errors: { line: number, message: string }[]
}
