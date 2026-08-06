import { expect, test } from '@playwright/test'

// ⚠️ Spec de DÉMONSTRATION (non destinée à la CI) : parcours de recette filmé sur le compte
// de démonstration seedé `recette@plume.fr` (données riches). Enregistrement vidéo activé.
// À lancer manuellement : `npx playwright test recette.spec`. Ne pas committer dans la suite CI
// (le compte démo n'existe pas dans la base e2e de la CI).

const EMAIL = 'recette@plume.fr'
const PASSWORD = 'recette-2026'
const PAUSE = 1500 // pauses délibérées : rendre la vidéo regardable

test.use({
  viewport: { width: 1366, height: 900 },
  video: { mode: 'on', size: { width: 1366, height: 900 } },
})

async function hydrate(page: import('@playwright/test').Page): Promise<void> {
  await page.waitForFunction(() => {
    const root = document.querySelector('#__nuxt')
    return root !== null && '__vue_app__' in root
  })
}

test('Recette Plume — parcours utilisateur complet (compte de démonstration)', async ({ page }) => {
  test.setTimeout(180_000)

  // 1) Connexion
  await page.goto('/login')
  await hydrate(page)
  await page.getByRole('textbox').first().fill(EMAIL)
  await page.locator('input[type="password"]').fill(PASSWORD)
  await page.waitForTimeout(PAUSE)
  await page.getByRole('button', { name: /se connecter|sign in/i }).click()
  await page.waitForURL('**/today')
  await hydrate(page)
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await page.waitForTimeout(PAUSE * 2) // 2) Écran « Aujourd'hui » (accueil : objectif, relances)

  // 3) Répertoire — organisations & contacts
  await page.goto('/organizations')
  await hydrate(page)
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await page.waitForTimeout(PAUSE * 2)

  // 4) Pipeline — kanban des pistes
  await page.goto('/leads')
  await hydrate(page)
  await page.waitForTimeout(PAUSE)
  // Ouvrir la première piste (carte du kanban) — exclut le bouton « Nouvelle piste » (/leads/new)
  const firstLead = page.locator('a[href^="/leads/"]:not([href="/leads/new"])').first()
  if (await firstLead.count()) {
    await firstLead.click()
    await page.waitForURL(/\/leads\/[0-9a-f-]+$/)
    await hydrate(page)
    await page.waitForTimeout(PAUSE * 2) // 5) Fiche piste : timeline, actions, brouillons
  }

  // 6) Tableau de bord
  await page.goto('/dashboard')
  await hydrate(page)
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await page.waitForTimeout(PAUSE * 2)

  // 7) « À trier » — relève des annonces (Sourcing)
  await page.goto('/candidates')
  await hydrate(page)
  await page.waitForTimeout(PAUSE)
  await page.getByRole('button', { name: /relever les annonces|fetch announcements/i }).first().click()
  await expect(page.getByRole('button', { name: /^accepter$|^accept$/i }).first()).toBeVisible()
  await page.waitForTimeout(PAUSE * 2)

  // 8) Réglages → Sources : ajouter puis retirer un flux RSS (avec confirmation)
  await page.goto('/settings?tab=sources')
  await hydrate(page)
  await expect(page.getByText(/sources d'annonces|announcement sources/i)).toBeVisible()
  await page.waitForTimeout(PAUSE)
  const url = 'https://demo-recette.example/rss'
  await page.getByPlaceholder(/rss/i).fill(url)
  await page.getByRole('button', { name: /ajouter le flux|add feed/i }).click()
  const row = page.locator('li', { hasText: url })
  await expect(row).toBeVisible()
  await page.waitForTimeout(PAUSE)
  await row.getByRole('button', { name: /retirer|remove/i }).click()
  await page.getByRole('dialog').getByRole('button', { name: /retirer|remove/i }).click()
  await expect(page.getByText(url)).toBeHidden()
  await page.waitForTimeout(PAUSE)
})
