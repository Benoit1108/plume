import { describe, expect, it } from 'vitest'
import { formatResponseDelay } from '../utils/dashboard/responseDelay'

/** Restitue « unité:valeur » pour vérifier l'unité CHOISIE, pas la traduction. */
const fmt = (unit: string, value: number): string => `${unit}:${value}`

describe('formatResponseDelay', () => {
  it('garde les jours au-delà de 24 h', () => {
    expect(formatResponseDelay(3, fmt)).toBe('days:3')
    expect(formatResponseDelay(1, fmt)).toBe('days:1')
    expect(formatResponseDelay(2.46, fmt)).toBe('days:2.5')
  })

  it('bascule en heures sous un jour — « 0 j » effaçait une réponse en 4 h', () => {
    expect(formatResponseDelay(0.1667, fmt)).toBe('hours:4')
    expect(formatResponseDelay(0.9, fmt)).toBe('hours:22')
  })

  it('bascule en minutes sous une heure, jamais « 0 »', () => {
    expect(formatResponseDelay(0.02, fmt)).toBe('minutes:29')
    expect(formatResponseDelay(0.0001, fmt)).toBe('minutes:1')
    expect(formatResponseDelay(0, fmt)).toBe('minutes:1')
  })

  it('affiche un tiret quand la donnée n\'existe pas', () => {
    expect(formatResponseDelay(null, fmt)).toBe('—')
    expect(formatResponseDelay(Number.NaN, fmt)).toBe('—')
    expect(formatResponseDelay(-1, fmt)).toBe('—')
  })
})
