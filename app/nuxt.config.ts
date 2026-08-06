// https://nuxt.com/docs/api/configuration/nuxt-config
export default defineNuxtConfig({
  compatibilityDate: '2025-07-01',
  devtools: { enabled: true },

  // SPA assumée (ADR-0022 / cadrage chantier 3) : Plume est un outil PRIVÉ authentifié → aucun
  // besoin SEO, et le SSR n'était de toute façon pas exploité (chaque useAsyncData est en
  // `server: false`). On garde le serveur Nitro (build + `node .output/server/index.mjs`) pour le
  // proxy /api même-origine (cookies httpOnly) — NE PAS passer en full-static.
  ssr: false,

  // Transition de route sobre (fondu + léger décalage) ; respecte prefers-reduced-motion
  // via les classes .page-* de main.css (neutralisées sous mouvement réduit).
  app: {
    pageTransition: { name: 'page', mode: 'out-in' },

    // `head` STATIQUE : en SPA (ssr:false) c'est le seul <head> présent dans l'HTML généré, donc
    // le seul que voient les robots et les aperçus de lien (LinkedIn/WhatsApp/Slack n'exécutent
    // pas de JS). Les surcharges par page (`useSeoMeta`) ne servent qu'à l'onglet côté client.
    // Valeurs en français = locale par défaut ; `lang` est réaligné au runtime dans app.vue.
    head: {
      htmlAttrs: { lang: 'fr' },
      title: 'Plume — le CRM de prospection des traductrices',
      meta: [
        { name: 'description', content: 'Repérez les bonnes maisons d\'édition, studios et agences, écrivez des messages qui sonnent juste, relancez au bon moment et suivez chaque réponse. Essai 14 jours, sans carte bancaire.' },
        { property: 'og:site_name', content: 'Plume' },
        { property: 'og:type', content: 'website' },
        { property: 'og:locale', content: 'fr_FR' },
        { property: 'og:title', content: 'Plume — le CRM de prospection des traductrices' },
        { property: 'og:description', content: 'Décrochez plus de clients, sans y passer vos journées. Essai 14 jours, sans carte bancaire.' },
        { name: 'twitter:card', content: 'summary_large_image' },
        // og:url + og:image (absolus) : à renseigner au 1er déploiement, quand le domaine existe
        // (cf. docs/ops/deploiement-vps.md) — un aperçu de lien sans image reste valide.
      ],
    },
  },

  // @nuxt/ui : composants + thème clair/sombre (color-mode) + design tokens.
  // @nuxtjs/i18n : UI bilingue FR/EN (cf. ADR-0011).
  modules: ['@nuxt/ui', '@nuxt/eslint', '@pinia/nuxt', '@nuxtjs/i18n'],

  // Composants rangés par DOMAINE en sous-dossiers (components/admin, /lead, /ui…). pathPrefix:false
  // → le nom reste le nom de fichier (pas de préfixe de chemin) : le sous-dossiering ne renomme rien.
  components: [{ path: '~/components', pathPrefix: false }],

  // Auto-import des hooks TanStack Query (chantier 3, lot D) — évite un import par page.
  // `dirs` en globs récursifs : composables/ et utils/ sont rangés par domaine en sous-dossiers, or
  // l'auto-import Nuxt n'est PAS récursif par défaut → on l'étend explicitement.
  imports: {
    dirs: ['composables/**', 'utils/**'],
    presets: [{ from: '@tanstack/vue-query', imports: ['useQuery', 'useMutation', 'useQueryClient'] }],
  },

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
