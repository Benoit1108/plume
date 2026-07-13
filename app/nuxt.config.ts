// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-01',
  devtools: { enabled: true },

  // @nuxt/ui : composants + thème clair/sombre (color-mode) + design tokens.
  // @nuxtjs/i18n : UI bilingue FR/EN (cf. ADR-0011).
  modules: ['@nuxt/ui', '@nuxt/eslint', '@pinia/nuxt', '@nuxtjs/i18n'],

  css: ['~/assets/css/main.css'],

  // Thème sombre par défaut (clair disponible via bascule).
  // classSuffix:'' -> la classe appliquée est `dark` (attendue par Tailwind/Nuxt UI).
  colorMode: {
    preference: 'dark',
    fallback: 'dark',
    classSuffix: '',
  },

  i18n: {
    defaultLocale: 'fr',
    strategy: 'no_prefix',
    locales: [
      { code: 'fr', name: 'Français', file: 'fr.json' },
      { code: 'en', name: 'English', file: 'en.json' },
    ],
    detectBrowserLanguage: {
      useCookie: true,
      cookieKey: 'plume_locale',
    },
  },

  runtimeConfig: {
    public: {
      // URL de l'API Symfony (surchargeable par NUXT_PUBLIC_API_BASE).
      apiBase: 'https://localhost:8443',
    },
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },
})
