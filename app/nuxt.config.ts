// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-01',
  devtools: { enabled: true },

  // Transition de route sobre (fondu + léger décalage) ; respecte prefers-reduced-motion
  // via les classes .page-* de main.css (neutralisées sous mouvement réduit).
  app: {
    pageTransition: { name: 'page', mode: 'out-in' },
  },

  // @nuxt/ui : composants + thème clair/sombre (color-mode) + design tokens.
  // @nuxtjs/i18n : UI bilingue FR/EN (cf. ADR-0011).
  modules: ['@nuxt/ui', '@nuxt/eslint', '@pinia/nuxt', '@nuxtjs/i18n'],

  css: ['~/assets/css/main.css'],

  // Icônes : tout ce qui est référencé dans les sources est embarqué dans le
  // bundle client, et l'endpoint de secours vit HORS de /api — sinon le proxy
  // dev (/api -> API Symfony) l'avale et les icônes non bundlées font 404.
  icon: {
    localApiEndpoint: '/_nuxt_icon',
    clientBundle: {
      scan: true,
      sizeLimitKb: 512,
    },
  },

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

  // Prod (et build E2E) : même besoin de même-origine que le dev — les cookies
  // httpOnly SameSite=Lax ne voyagent qu'en première partie. Le serveur Nitro
  // relaie /api vers l'API (cible fixée AU BUILD via NUXT_API_PROXY_TARGET).
  $production: {
    nitro: {
      routeRules: {
        '/api/**': {
          proxy: `${process.env.NUXT_API_PROXY_TARGET ?? 'https://localhost:8443'}/api/**`,
        },
      },
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
