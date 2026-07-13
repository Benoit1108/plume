import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    environment: 'node',
    include: ['tests/**/*.spec.ts'],
    coverage: {
      provider: 'v8',
      include: ['composables/**', 'stores/**', 'utils/**'],
      reporter: ['text', 'clover'],
      // Seuils bloquants : la CI échoue si la couverture régresse.
      thresholds: {
        statements: 85,
        branches: 80,
        functions: 75,
        lines: 85,
      },
    },
  },
})
