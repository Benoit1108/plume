import { defineStore } from 'pinia'

/**
 * Préférences UI persistées (cookie) : locale, thème, fuseau horaire.
 * Miroir front du value object Preferences (contexte Account) — cf. ADR-0011.
 * TODO M1 : synchroniser avec le profil serveur une fois l'utilisatrice authentifiée.
 */
export const usePreferencesStore = defineStore('preferences', () => {
  const locale = useCookie<string>('plume_locale', { default: () => 'fr' })
  const theme = useCookie<string>('plume_theme', { default: () => 'system' })
  const timezone = useCookie<string>('plume_tz', { default: () => 'Europe/Paris' })

  return { locale, theme, timezone }
})
