import { describe, expect, it } from 'vitest'
import { toOrganizationInput } from '../utils/organization-form'

describe('toOrganizationInput', () => {
  it('normalise la saisie complète', () => {
    const input = toOrganizationInput({
      name: '  Actes Sud  ',
      type: 'PUBLISHER',
      website: ' https://actes-sud.fr ',
      country: ' fr ',
      workingLanguages: ' EN, fr  es ',
      segments: ['PUBLISHING'],
      notes: '  à relancer  ',
    })

    expect(input).toEqual({
      name: 'Actes Sud',
      type: 'PUBLISHER',
      website: 'https://actes-sud.fr',
      country: 'FR',
      workingLanguages: ['en', 'fr', 'es'],
      segments: ['PUBLISHING'],
      notes: 'à relancer',
    })
  })

  it('null-ifie les champs optionnels vides', () => {
    const input = toOrganizationInput({
      name: 'X',
      type: 'OTHER',
      website: '   ',
      country: '',
      workingLanguages: '',
      segments: [],
      notes: '',
    })

    expect(input.website).toBeNull()
    expect(input.country).toBeNull()
    expect(input.workingLanguages).toEqual([])
    expect(input.notes).toBeNull()
  })
})
