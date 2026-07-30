import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

/**
 * Annuaire suggéré : la page rend le catalogue et l'ajout au Répertoire fonctionne. Robuste au tenant
 * e2e partagé (runs répétés) : on ajoute si une entrée est ajoutable, sinon tout est déjà importé.
 * Sélecteurs BILINGUES (le build E2E peut rendre en fr ou en en).
 */
test('annuaire suggéré : rendu et ajout au Répertoire', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  const response = await page.goto('/organizations/catalog')
  expect(response?.status()).toBe(200)
  await waitForHydration(page)
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()

  const addButtons = page.getByRole('button', { name: /Ajouter|^Add$/i })
  if (await addButtons.count() > 0) {
    await addButtons.first().click()
    // Succès (toast) OU l'entrée bascule en « déjà présent ».
    await expect(
      page.getByText(/ajoutée au Répertoire|added to your Directory|déjà dans (le Répertoire|votre Répertoire)|already in (Directory|your Directory)/i).first(),
    ).toBeVisible()
  }
  else {
    // Toutes déjà importées sur ce tenant.
    await expect(page.getByText(/Déjà dans le Répertoire|Already in Directory/i).first()).toBeVisible()
  }

  expect(errors).toEqual([])
})
