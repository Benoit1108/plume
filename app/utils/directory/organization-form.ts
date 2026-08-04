import type { OrganizationInput, OrganizationType } from '~/types/directory'

/** État interne du formulaire organisation (chaînes brutes de saisie). */
export interface OrgFormModel {
  name: string
  type: OrganizationType
  website: string
  country: string
  workingLanguages: string
  segments: string[]
  notes: string
}

/** Normalise la saisie vers le contrat API : trim, casse, listes, null-ification. */
export function toOrganizationInput(form: OrgFormModel): OrganizationInput {
  return {
    name: form.name.trim(),
    type: form.type,
    website: form.website.trim() || null,
    country: form.country.trim().toUpperCase() || null,
    workingLanguages: form.workingLanguages.split(/[\s,]+/).map(s => s.trim().toLowerCase()).filter(Boolean),
    segments: form.segments,
    notes: form.notes.trim() || null,
  }
}
