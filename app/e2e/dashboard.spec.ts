import { expect, test } from '@playwright/test'
import { createLeadViaUi, login, waitForHydration, watchConsole } from './helpers'

test('tableau de bord : un pipeline joué produit des chiffres cohérents', async ({ page }) => {
  const errors = watchConsole(page)
  const orgName = `E2E Dash ${Date.now()}`

  await login(page)

  // Jouer une piste jusqu'à la victoire (contact → réponse → gagnée).
  await createLeadViaUi(page, orgName)
  await page.getByRole('button', { name: /^contacter$|^contact$/i }).click()
  await page.getByRole('button', { name: /réponse reçue|reply received/i }).click()
  await page.getByRole('button', { name: /^gagnée$|^won$/i }).click()
  await expect(page.getByText(/gagnée|won/i).first()).toBeVisible()

  // Le tableau de bord reflète le parcours (les KPIs sont en texte : a11y).
  await page.goto('/dashboard')
  await waitForHydration(page)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(/tableau de bord|dashboard/i)
  await expect(page.getByText(/taux de réponse|response rate/i)).toBeVisible()
  await expect(page.getByText(/pistes contactées|contacted leads/i)).toBeVisible()
  await expect(page.getByText(/conversion/i).first()).toBeVisible()
  // Pipeline + activité + segments présents.
  await expect(page.getByText(/^pipeline$/i)).toBeVisible()
  await expect(page.getByText(/8 dernières semaines|last 8 weeks/i)).toBeVisible()
  await expect(page.getByText(/par segment|by segment/i)).toBeVisible()

  expect(errors).toEqual([])
})
