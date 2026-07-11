export const SUPPORTED_LOCALES = ['fr', 'en'] as const

export type SupportedLocale = (typeof SUPPORTED_LOCALES)[number]

/** Miroir front du VO Preferences (contexte Account) : locales UI supportées. */
export function isSupportedLocale(code: string): code is SupportedLocale {
  return (SUPPORTED_LOCALES as readonly string[]).includes(code)
}
