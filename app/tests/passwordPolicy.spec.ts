import { describe, expect, it } from 'vitest'
import { PASSWORD_MIN_LENGTH, assessPassword } from '../utils/account/passwordPolicy'

describe('assessPassword — règles', () => {
  it('exige longueur, minuscule, majuscule et caractère spécial', () => {
    const { rules, satisfied } = assessPassword('Bonjour!23')
    expect(rules).toEqual({ length: true, lowercase: true, uppercase: true, special: true })
    expect(satisfied).toBe(true)
  })

  it('signale précisément CE QUI manque, une règle à la fois', () => {
    expect(assessPassword('bonjour!23').rules.uppercase).toBe(false)
    expect(assessPassword('BONJOUR!23').rules.lowercase).toBe(false)
    expect(assessPassword('Bonjour123').rules.special).toBe(false)
    expect(assessPassword('Bo!1').rules.length).toBe(false)
  })

  it('accepte les accents comme lettres, et la ponctuation comme caractère spécial', () => {
    const { satisfied } = assessPassword('Éléphant-rose')
    expect(satisfied).toBe(true)
  })

  it(`la règle de longueur suit la constante partagée (${PASSWORD_MIN_LENGTH})`, () => {
    expect(assessPassword('A!b'.padEnd(PASSWORD_MIN_LENGTH - 1, 'x')).rules.length).toBe(false)
    expect(assessPassword('A!b'.padEnd(PASSWORD_MIN_LENGTH, 'x')).rules.length).toBe(true)
  })
})

describe('assessPassword — robustesse', () => {
  it('note zéro pour un champ vide, et monte avec la longueur et la variété', () => {
    expect(assessPassword('').score).toBe(0)
    const short = assessPassword('Abcdef1!').score
    const long = assessPassword('Abcdef1!Ghijkl2?').score // pas une répétition : la garde ne s'applique pas
    expect(long).toBeGreaterThan(short)
    expect(long).toBeLessThanOrEqual(4)
  })

  it('ne récompense pas une répétition, si longue soit-elle', () => {
    expect(assessPassword('abababababababab').score).toBeLessThanOrEqual(1)
    expect(assessPassword('aaaaaaaaaaaaaaaa').score).toBeLessThanOrEqual(1)
  })

  it('sépare robustesse et validité : un mot de passe long mais sans majuscule reste refusé', () => {
    const assessment = assessPassword('bonjour-le-monde-2026!')
    expect(assessment.score).toBeGreaterThan(1)
    expect(assessment.satisfied).toBe(false)
  })
})
