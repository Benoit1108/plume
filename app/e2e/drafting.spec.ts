import type { Page } from '@playwright/test'
import { expect, test } from '@playwright/test'

const E2E_EMAIL = 'e2e@plume.test'
const E2E_PASSWORD = 'e2e-secret-123'

function watchConsole(page: Page): string[] {
  const errors: string[] = []
  page.on('console', (message) => {
    if (message.type() === 'error') errors.push(`console.error: ${message.text()}`)
    if (message.type() === 'warning' && message.text().includes('Hydration')) {
      errors.push(`hydration: ${message.text()}`)
    }
  })
  page.on('pageerror', error => errors.push(`pageerror: ${error.message}`))
  return errors
}

async function waitForHydration(page: Page): Promise<void> {
  await page.waitForFunction(() => {
    const root = document.querySelector('#__nuxt')
    return root !== null && '__vue_app__' in root
  })
}

async function login(page: Page): Promise<void> {
  await page.goto('/login')
  await waitForHydration(page)
  await page.getByRole('textbox').first().fill(E2E_EMAIL)
  await page.locator('input[type="password"]').fill(E2E_PASSWORD)
  await page.getByRole('button', { name: /se connecter|sign in/i }).click()
  await page.waitForURL('**/today')
}

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

  // Organisation + piste cibles.
  await page.goto('/organizations/new')
  await waitForHydration(page)
  await page.getByRole('textbox').first().fill(orgName)
  await page.getByRole('button', { name: /créer|create/i }).click()
  await page.waitForURL(/\/organizations\/[0-9a-f-]+$/)
  await page.getByRole('link', { name: /créer une piste|create a lead/i }).click()
  await page.waitForURL(/\/leads\/new/)
  await waitForHydration(page)
  await page.getByRole('button', { name: /créer|create/i }).click()
  await page.waitForURL(/\/leads\/[0-9a-f-]+$/)

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
  // (.last() : l'icône près de l'objet porte le même libellé accessible.)
  await page.getByRole('button', { name: /^copier$|^copy$/i }).last().click()
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
