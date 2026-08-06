import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

/**
 * Smoke de la page Compte (P0-5 de la revue globale : le socle d'ouverture — 2FA, sessions,
 * export, suppression — n'était couvert que par des tests d'API, jamais par le rendu réel).
 *
 * La page est à ONGLETS (?tab=) : on vise l'onglet dans l'URL, comme le font les liens entrants.
 *
 * NON DESTRUCTIF : on partage le tenant e2e avec les autres fichiers. On NE change PAS le mot de
 * passe, on N'active PAS la 2FA (on s'arrête à l'affichage de la clé), on NE supprime PAS le compte.
 */
test('la page Compte affiche toutes ses sections de sécurité sans erreur console', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  const response = await page.goto('/account')
  expect(response?.status()).toBe(200)
  await waitForHydration(page)

  // Sélecteurs BILINGUES : le build E2E peut rendre en fr OU en en (locale du navigateur).
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()

  // Onglet Identité (par défaut) → puis Sécurité → puis Mes données.
  await expect(page.getByText(/Nom d'affichage|Display name/i)).toBeVisible()

  await page.getByRole('tab', { name: /Sécurité|Security/i }).click()
  await expect(page.getByText(/Double authentification|Two-factor authentication/i)).toBeVisible()
  await expect(page.getByText(/Sessions actives|Active sessions/i)).toBeVisible()

  await page.getByRole('tab', { name: /Mes données|My data/i }).click()
  await expect(page.getByRole('button', { name: /Exporter mes données|Export my data/i })).toBeVisible()
  await expect(page.getByRole('button', { name: /Supprimer mon compte|Delete my account/i })).toBeVisible()

  expect(errors).toEqual([])
})

test('un lien peut viser directement un onglet du Compte', async ({ page }) => {
  await login(page)
  await page.goto('/account?tab=data')
  await waitForHydration(page)

  await expect(page.getByRole('button', { name: /Exporter mes données|Export my data/i })).toBeVisible()
})

test('l\'enrôlement 2FA révèle une clé secrète et reste abandonnable', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await page.goto('/account?tab=security')
  await waitForHydration(page)

  // Attendre que la section Sécurité soit RENDUE avant de tester la présence du bouton : son contenu
  // est derrière le chargement (async) du profil, et count() — contrairement à toBeVisible() —
  // n'attend pas (sinon course → faux négatif sur le bouton « Activer »).
  await expect(page.getByText(/Double authentification|Two-factor authentication/i)).toBeVisible()

  const enableName = /Activer la 2FA|Enable 2FA/i
  const enable = page.getByRole('button', { name: enableName })
  // Si un run précédent a laissé la 2FA active, le bouton n'existe pas : on vérifie alors l'état actif.
  if (await enable.count() > 0) {
    await enable.click()

    // L'enrôlement affiche le QR à scanner + la clé en repli (saisie manuelle).
    await expect(page.getByRole('img', { name: /QR code/i })).toBeVisible()
    await expect(page.getByText(/saisissez cette clé manuellement|enter this key manually/i)).toBeVisible()
    // Le bouton de confirmation reste désactivé tant que le code (6 chiffres) n'est pas saisi.
    await expect(page.getByRole('button', { name: /Confirmer et activer|Confirm and enable/i })).toBeDisabled()

    // On abandonne (on ne confirme jamais) : recharger la page ne laisse aucune 2FA active.
    await page.reload()
    await waitForHydration(page)
    await expect(page.getByRole('button', { name: enableName })).toBeVisible()
  }
  else {
    await expect(page.getByText(/2FA active/i).first()).toBeVisible()
  }

  expect(errors).toEqual([])
})
