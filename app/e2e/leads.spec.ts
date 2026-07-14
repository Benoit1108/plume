import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

test('parcours pipeline : organisation → piste → contact → réponse → gagnée, journal projeté', async ({ page }) => {
  const errors = watchConsole(page)
  const orgName = `E2E Pipeline ${Date.now()}`

  await login(page)

  // Créer l'organisation cible.
  await page.goto('/organizations/new')
  await waitForHydration(page)
  await page.getByRole('textbox').first().fill(orgName)
  await page.getByRole('button', { name: /créer|create/i }).click()
  await page.waitForURL(/\/organizations\/[0-9a-f-]+$/)

  // Créer la piste depuis la fiche organisation (bouton contextualisé).
  await page.getByRole('link', { name: /créer une piste|create a lead/i }).click()
  await page.waitForURL(/\/leads\/new/)
  await waitForHydration(page)
  await page.getByRole('button', { name: /créer|create/i }).click()

  // Fiche piste : statut initial + actions légales seulement.
  await page.waitForURL(/\/leads\/[0-9a-f-]+$/)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(orgName)
  await expect(page.getByRole('button', { name: /^contacter$|^contact$/i })).toBeVisible()

  // Contact → réponse → discussion (le bouton suivant apparaît après chaque refresh).
  await page.getByRole('button', { name: /^contacter$|^contact$/i }).click()
  await page.getByRole('button', { name: /réponse reçue|reply received/i }).click()
  await page.getByRole('button', { name: /^gagnée$|^won$/i }).click()

  // Statut terminal : plus aucune action de transition.
  await expect(page.getByText(/gagnée|won/i).first()).toBeVisible()

  // Note + journal (projection asynchrone : le worker tourne, on attend l'apparition).
  await page.getByRole('textbox', { name: /ajouter une note|add a note/i }).fill('Note E2E')
  await page.getByRole('button', { name: /ajouter une note|add a note/i }).click()
  await expect(page.getByText('Note E2E')).toBeVisible({ timeout: 15_000 })
  await expect(page.getByText(/contact établi|contact made/i)).toBeVisible({ timeout: 15_000 })

  // Kanban : la piste gagnée est dans la colonne Gagnée.
  await page.goto('/leads')
  await waitForHydration(page)
  const wonColumn = page.getByRole('region', { name: /gagnée|won/i })
  await expect(wonColumn.getByText(orgName)).toBeVisible()

  expect(errors).toEqual([])
})
