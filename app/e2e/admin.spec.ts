import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

/**
 * Cloisonnement du back-office (P0-5 de la revue globale : les routes /admin ne doivent jamais
 * fuiter à une traductrice). L'utilisateur e2e est un compte ORDINAIRE (ROLE_USER) — le seul
 * qu'on sait provisionner en CI. On vérifie donc la GARDE, côté client ET côté serveur (l'autorité).
 *
 * Le happy-path admin (liste des comptes, reset 2FA) exige un admin AVEC 2FA enrôlée — il est
 * couvert par AdminApiTest côté API et fera partie de la grande recette manuelle.
 */
test('un compte ordinaire ne voit pas l\'entrée back-office', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await waitForHydration(page)

  // L'entrée de navigation n'existe pas dans le DOM pour un non-admin (témoin isAdmin=false).
  // Bilingue (le build E2E peut rendre en fr OU en en).
  await expect(page.getByRole('link', { name: /Back-office|Back office/i })).toHaveCount(0)

  expect(errors).toEqual([])
})

test('l\'API admin refuse un compte non-admin (403 — l\'autorité serveur)', async ({ page }) => {
  await login(page)

  // Même authentifié, sans ROLE_ADMIN les endpoints /admin répondent 403 (access_control).
  const overview = await page.request.get('/api/v1/admin/overview')
  expect(overview.status()).toBe(403)

  const accounts = await page.request.get('/api/v1/admin/accounts')
  expect(accounts.status()).toBe(403)
})
