import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

// Boucle d'ingestion RSS (M3.1a) : relever une source (FakeAlertSource par défaut — 2 annonces
// démo à guid FIXES) fait entrer des annonces dans « À trier ». Le test est agnostique à l'état
// initial du tenant e2e (partagé, persistant) : la relève est idempotente (dédoublonnage par
// guid), donc l'annonce de démo est visible qu'elle vienne d'être ingérée ou d'un run précédent.
// On ne trie PAS ici : un tri est irréversible (anti-réapparition ADR-0021) et casserait
// l'idempotence ; les décisions de tri sont couvertes par les tests fonctionnels.
test('file « À trier » : relever une source RSS fait entrer les annonces', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await page.goto('/candidates')
  await waitForHydration(page)

  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()

  await page.getByRole('button', { name: /relever les annonces|fetch announcements/i }).first().click()

  // Au moins une carte triable apparaît (on n'assère pas un titre précis : robuste au tenant
  // e2e persistant, où une annonce démo a pu être triée par un run antérieur).
  await expect(page.getByRole('button', { name: /^accepter$|^accept$/i }).first()).toBeVisible()

  expect(errors).toEqual([])
})
