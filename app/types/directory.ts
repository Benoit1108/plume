export type OrganizationType = 'PUBLISHER' | 'AV_STUDIO' | 'AGENCY' | 'OTHER'

export interface Contact {
  id?: string
  fullName: string
  role?: string | null
  email?: string | null
  phone?: string | null
  linkedinUrl?: string | null
  preferredLanguage?: string | null
  doNotContact?: boolean
}

export interface Organization {
  id?: string
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
