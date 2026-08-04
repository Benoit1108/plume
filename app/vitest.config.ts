import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    environment: 'node',
    include: ['tests/**/*.spec.ts'],
    coverage: {
      provider: 'v8',
      include: ['composables/**', 'stores/**', 'utils/**'],
      // useLandingMotion = pur pilotage DOM/animation (IntersectionObserver, canvas, rAF, listeners)
      // de la vitrine, intestable en env `node` — couvert par l'E2E smoke, exclu de l'unité comme
      // les composants .vue.
      exclude: ['composables/landing/useLandingMotion.ts'],
      reporter: ['text', 'clover'],
      // Seuils bloquants : la CI échoue si la couverture régresse.
      // perFile : chaque fichier doit tenir le seuil — un module non testé ne peut
      // plus se cacher derrière la moyenne des modules bien couverts.
      thresholds: {
        perFile: true,
        statements: 85,
        branches: 80,
        functions: 75,
        lines: 85,
      },
    },
  },
})
