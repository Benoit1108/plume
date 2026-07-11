import { describe, expect, it } from 'vitest'
import { isSupportedLocale, SUPPORTED_LOCALES } from '../utils/locale'

describe('isSupportedLocale', () => {
  it('accepte les locales supportées', () => {
    for (const locale of SUPPORTED_LOCALES) {
      expect(isSupportedLocale(locale)).toBe(true)
    }
  })

  it('rejette une locale inconnue', () => {
    expect(isSupportedLocale('de')).toBe(false)
  })
})
