// @ts-check
import withNuxt from './.nuxt/eslint.config.mjs'

export default withNuxt({
  // Types générés depuis le contrat OpenAPI (npm run gen:types) — non lintés.
  ignores: ['types/api-generated.ts'],
  rules: {
    // M2.0 — garde anti-XSS : des contenus d'emails TIERS arrivent avec la
    // passerelle (M2.3). Tout affichage passe par l'interpolation texte ;
    // v-html est interdit, pas seulement déconseillé.
    'vue/no-v-html': 'error',
  },
})
