import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

// Le flux de tri complet (annonce → accepter → piste) attend un point d'entrée
// d'ingestion (M3.1 RSS) : le navigateur ne peut pas seeder de candidate. On garde
// ici la garde console/hydratation + l'état vide sur le tenant e2e (aucune annonce).
test('la file de tri « À trier » se charge sans erreur (vide pour le tenant e2e)', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await page.goto('/candidates')
  await waitForHydration(page)

  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await expect(page.getByText(/rien à trier|nothing to triage/i)).toBeVisible()

  expect(errors).toEqual([])
})
