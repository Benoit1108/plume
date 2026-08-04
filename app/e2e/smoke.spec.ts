import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

test('un visiteur non connecté est redirigé vers /login', async ({ page }) => {
  const errors = watchConsole(page)

  await page.goto('/organizations')
  await page.waitForURL('**/login')
  await expect(page.locator('input[type="password"]')).toBeVisible()

  expect(errors).toEqual([])
})

test('la racine / affiche la vitrine publique au visiteur', async ({ page }) => {
  const errors = watchConsole(page)

  await page.goto('/')
  // Pas de redirection vers /login : la vitrine est publique.
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await expect(page.getByRole('link', { name: /se connecter|log in/i }).first()).toBeVisible()
  // Le CTA d'inscription mène bien à l'inscription.
  await page.getByRole('link', { name: /démarrer l'essai|start free trial/i }).first().click()
  await page.waitForURL('**/register')

  expect(errors).toEqual([])
})

test('« Essayer la démo » monte un compte éphémère et connecte le visiteur', async ({ page }) => {
  const errors = watchConsole(page)

  await page.goto('/')
  // Le bouton « Essayer la démo » figure dans le hero ET le bandeau CTA de bas de page.
  await page.getByRole('button', { name: /essayer la démo|try the demo/i }).first().click()

  // Login sans mot de passe : l'API pose les cookies et on arrive dans l'app.
  await page.waitForURL('**/today')
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  // Le bandeau « mode démonstration » signale les données fictives.
  await expect(page.getByText(/vous explorez une démonstration|you are exploring a demo/i)).toBeVisible()

  expect(errors).toEqual([])
})

test('login puis Répertoire : rendu complet sans erreur console', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await page.goto('/organizations')
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await waitForHydration(page)
  // Le filtre de type (USelect) doit être opérationnel — régression Reka « value ='' ».
  await page.getByRole('combobox').first().click()
  await expect(page.getByRole('option').first()).toBeVisible()
  await page.keyboard.press('Escape')

  expect(errors).toEqual([])
})

test('accès direct SSR au Répertoire authentifié : pas de 500, pas de mismatch', async ({ page }) => {
  await login(page)

  const errors = watchConsole(page)
  // Nouvelle navigation complète (SSR) avec les cookies posés — le cas qui plantait.
  const response = await page.goto('/organizations')

  expect(response?.status()).toBe(200)
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  expect(errors).toEqual([])
})

test('création d\'une organisation puis fiche', async ({ page }) => {
  const errors = watchConsole(page)
  const name = `E2E ${Date.now()}`

  await login(page)
  await page.goto('/organizations/new')
  await waitForHydration(page)
  await page.getByRole('textbox').first().fill(name)
  await page.getByRole('button', { name: /créer|create/i }).click()

  // Redirigé sur la fiche, le nom est affiché.
  await page.waitForURL(/\/organizations\/[0-9a-f-]+$/)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(name)

  // Retour liste : l'organisation apparaît. On PASSE PAR LA RECHERCHE (le tenant
  // e2e partagé accumule des centaines d'orgs sur plusieurs pages — cf. revue M1).
  await page.goto('/organizations')
  await waitForHydration(page)
  await page.getByRole('textbox', { name: /rechercher|search/i }).fill(name)
  await expect(page.getByRole('link', { name: new RegExp(name) }).first()).toBeVisible()

  expect(errors).toEqual([])
})

test('l\'import CSV et le login restent sains (SSR + console)', async ({ page }) => {
  await login(page)
  const errors = watchConsole(page)

  const importPage = await page.goto('/organizations/import')
  expect(importPage?.status()).toBe(200)
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()

  expect(errors).toEqual([])
})
