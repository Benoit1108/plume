import { expect, test } from '@playwright/test'
import { createLeadViaUi, login, waitForHydration, watchConsole } from './helpers'

test('rédaction assistée : réglages profil → génération canned → relecture → copie → suppression', async ({ page, context }) => {
  const errors = watchConsole(page)
  const orgName = `E2E Plume ${Date.now()}`
  await context.grantPermissions(['clipboard-read', 'clipboard-write'])

  await login(page)

  // Réglages : bio + signature (matière première du prompt).
  await page.goto('/settings')
  await waitForHydration(page)
  await page.getByRole('textbox', { name: /^bio$/i }).fill('Traductrice indépendante EN>FR, dix ans en littérature jeunesse.')
  await page.getByRole('textbox', { name: /signature/i }).fill('Marie E2E')
  await page.getByRole('button', { name: /enregistrer|save/i }).click()
  await expect(page.getByText(/réglages enregistrés|settings saved/i).first()).toBeVisible()

  await createLeadViaUi(page, orgName)

  // Générer un brouillon (adaptateur canned : déterministe, gratuit).
  await page.getByRole('button', { name: /générer un message|generate a message/i }).click()
  await page.getByRole('button', { name: /^générer$|^generate$/i }).click()

  // Le brouillon passe READY (worker + rattrapage automatique).
  const draftRow = page.getByRole('button', { name: /mail de candidature|application email/i }).first()
  await expect(draftRow).toBeVisible()
  await expect(page.getByText(/prêt à relire|ready to review/i).first()).toBeVisible({ timeout: 20_000 })

  // Relecture : le corps contient l'organisation et la signature du profil.
  await draftRow.click()
  const body = page.getByRole('textbox', { name: /^message$/i })
  await expect(body).toBeVisible()
  await expect(body).toHaveValue(new RegExp(orgName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')))
  await expect(body).toHaveValue(/Marie E2E/)

  // Édition humaine + sauvegarde.
  await body.fill('Corps relu et corrigé par une humaine.\n\nMarie E2E')
  await page.getByRole('button', { name: /enregistrer|save/i }).click()
  await expect(page.getByText(/brouillon enregistré|draft saved/i).first()).toBeVisible()

  // Copier : le presse-papier reçoit le corps (pont vers le webmail en M1).
  await page.getByRole('button', { name: /copier le message|copy the message/i }).click()
  await expect(page.getByText(/copié dans le presse-papier|copied to clipboard/i).first()).toBeVisible()
  const clipboard = await page.evaluate(() => navigator.clipboard.readText())
  expect(clipboard).toContain('Corps relu et corrigé')

  // Suppression (avec confirmation).
  await page.getByRole('button', { name: /supprimer|delete/i }).first().click()
  await page.getByRole('button', { name: /^supprimer$|^delete$/i }).last().click()
  await expect(page.getByText(/brouillon supprimé|draft deleted/i).first()).toBeVisible()

  expect(errors).toEqual([])
})

test('modèles : les 3 gabarits de départ sont seedés et éditables', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await page.goto('/templates')
  await waitForHydration(page)

  // Seed à la première utilisation (idempotent) — au moins les gabarits de départ.
  await expect(page.getByText('Candidature édition (FR)')).toBeVisible()
  await expect(page.getByText('Candidature audiovisuel (EN)')).toBeVisible()
  await expect(page.getByText('Relance (FR)')).toBeVisible()

  // Création d'un modèle personnalisé.
  const name = `Modèle E2E ${Date.now()}`
  await page.getByRole('button', { name: /nouveau modèle|new template/i }).click()
  await page.getByRole('textbox', { name: /^(nom|name)\*?$/i }).fill(name)
  await page.getByRole('textbox', { name: /^(corps|body)\*?$/i }).fill('Bonjour {{contact}}, {{signature}}')
  await page.getByRole('button', { name: /enregistrer|save/i }).click()
  await expect(page.getByText(/modèle créé|template created/i).first()).toBeVisible()
  await expect(page.getByText(name)).toBeVisible()

  // Suppression (avec confirmation) — on laisse le répertoire de gabarits propre.
  const row = page.locator('li', { hasText: name })
  await row.getByRole('button', { name: /supprimer|delete/i }).click()
  await page.getByRole('button', { name: /^supprimer$|^delete$/i }).last().click()
  await expect(page.getByText(/modèle supprimé|template deleted/i).first()).toBeVisible()

  expect(errors).toEqual([])
})
