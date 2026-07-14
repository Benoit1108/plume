import { defineConfig, devices } from '@playwright/test'

/**
 * E2E smoke : vraie navigation (SSR + hydratation + API réelle).
 * Attrape ce que build/type-check ne voient pas : 500 au rendu serveur,
 * mismatches d'hydratation, erreurs console au runtime.
 *
 * Par défaut, lance le BUILD DE PRODUCTION (déterministe — pas de réoptimisation
 * Vite à froid) qui appelle l'API directement (NUXT_PUBLIC_API_BASE), certificat
 * auto-signé ignoré par le navigateur de test. En local, si un serveur tourne
 * déjà sur :3000 (dev avec proxy /api), il est réutilisé tel quel.
 *
 * Prérequis : stack API démarrée (make up) + utilisateur e2e@plume.test
 * (`app:user:create` — cf. job e2e de la CI).
 */
export default defineConfig({
  testDir: './e2e',
  // Tous les tests partagent LE MÊME tenant e2e : il faut sérialiser entre
  // fichiers aussi (fullyParallel:false ne sérialise qu'au sein d'un fichier).
  fullyParallel: false,
  workers: 1,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: process.env.CI ? 'github' : 'list',
  use: {
    baseURL: process.env.PLAYWRIGHT_BASE_URL ?? 'http://localhost:3000',
    ignoreHTTPSErrors: true, // cert FrankenPHP auto-signé en dev/CI
    trace: 'retain-on-failure',
  },
  projects: [
    { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
  ],
  webServer: {
    command: 'npm run build && node .output/server/index.mjs',
    url: 'http://localhost:3000/login',
    reuseExistingServer: !process.env.CI,
    timeout: 300_000,
    env: {
      PORT: '3000',
      NUXT_PUBLIC_API_BASE: process.env.E2E_API_BASE ?? 'https://localhost:8443',
    },
  },
})
