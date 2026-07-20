import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

// Gestion des flux d'annonces (M3.1b) dans les Réglages. URL unique par run (tenant e2e
// partagé + persistant) et nettoyage en fin de test → idempotent. Ajouter un flux ne déclenche
// aucune relève réseau (seul « Relever » le ferait), donc l'URL factice est sans risque.
test('Réglages : ajouter puis retirer un flux d\'annonces RSS', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await page.goto('/settings')
  await waitForHydration(page)

  await expect(page.getByText(/sources d'annonces|announcement sources/i)).toBeVisible()

  const url = `https://e2e-feed-${Date.now()}.example/rss`

  await page.getByPlaceholder(/rss/i).fill(url)
  await page.getByRole('button', { name: /ajouter le flux|add feed/i }).click()

  const row = page.locator('li', { hasText: url })
  await expect(row).toBeVisible()

  // Nettoyage : retirer le flux (confirmation destructive) → il disparaît de la liste.
  await row.getByRole('button', { name: /retirer|remove/i }).click()
  const dialog = page.getByRole('dialog')
  await dialog.getByRole('button', { name: /retirer|remove/i }).click()
  await expect(page.getByText(url)).toBeHidden()

  expect(errors).toEqual([])
})
