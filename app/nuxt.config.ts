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
      // Vide = même origine : en dev, le proxy Nitro (nitro.devProxy) relaie /api vers l'API
      // (évite le certificat auto-signé côté navigateur + le CORS). En prod : URL de l'API.
      apiBase: '',
    },
  },

  // Dev : /api -> API Symfony (côté serveur Nuxt, ignore le cert auto-signé FrankenPHP).
  // Cible surchargeable (ex. dans Docker : https://php/api).
  $development: {
    nitro: {
      devProxy: {
        '/api': {
          target: process.env.NUXT_DEV_API_TARGET || 'https://localhost:8443/api',
          changeOrigin: true,
          secure: false,
        },
      },
    },
  },

  typescript: {
    strict: true,
    typeCheck: false,
  },
})
