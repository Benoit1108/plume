import { describe, expect, it } from 'vitest'
import { suggestDuplicateOrganizations } from '../utils/organizations'

const orgs = [
  { id: '1', name: 'Éditions du Nord' },
  { id: '2', name: 'Studio AV Démo' },
  { id: '3', name: 'éditions du nord (bis)' },
]

describe('suggestDuplicateOrganizations', () => {
  it('propose les organisations dont le nom correspond (casse/espaces ignorés, inclusion mutuelle)', () => {
    const hits = suggestDuplicateOrganizations('  Éditions du Nord  ', orgs)
    expect(hits.map(o => o.id)).toEqual(['1', '3']) // égalité normalisée + inclusion
  })

  it('reste muet en dessous de 3 caractères ou sans correspondance', () => {
    expect(suggestDuplicateOrganizations('éd', orgs)).toEqual([])
    expect(suggestDuplicateOrganizations('Agence Inexistante', orgs)).toEqual([])
  })

  it('borne le nombre de suggestions à 5', () => {
    const many = Array.from({ length: 8 }, (_, i) => ({ id: String(i), name: `Studio ${i}` }))
    expect(suggestDuplicateOrganizations('Studio', many)).toHaveLength(5)
  })
})
