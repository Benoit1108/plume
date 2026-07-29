import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

/**
 * Smoke de la page Compte (P0-5 de la revue globale : le socle d'ouverture — 2FA, sessions,
 * export, suppression — n'était couvert que par des tests d'API, jamais par le rendu réel).
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

  // Rendu complet : identité, 2FA, sessions, export RGPD, zone dangereuse.
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  await expect(page.getByText(/Double authentification/i)).toBeVisible()
  await expect(page.getByText(/Sessions actives/i)).toBeVisible()
  await expect(page.getByRole('button', { name: /Exporter mes données/i })).toBeVisible()
  await expect(page.getByRole('button', { name: /Supprimer mon compte/i })).toBeVisible()

  expect(errors).toEqual([])
})

test('l\'enrôlement 2FA révèle une clé secrète et reste abandonnable', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await page.goto('/account')
  await waitForHydration(page)

  const enable = page.getByRole('button', { name: /Activer la 2FA/i })
  // Si un run précédent a laissé la 2FA active, le bouton n'existe pas : on vérifie alors l'état actif.
  if (await enable.count() > 0) {
    await enable.click()

    // L'enrôlement affiche la clé à saisir dans l'app d'authentification.
    await expect(page.getByText(/saisie manuelle/i)).toBeVisible()
    // Le bouton de confirmation reste désactivé tant que le code (6 chiffres) n'est pas saisi.
    await expect(page.getByRole('button', { name: /Confirmer et activer/i })).toBeDisabled()

    // On abandonne (on ne confirme jamais) : recharger la page ne laisse aucune 2FA active.
    await page.reload()
    await waitForHydration(page)
    await expect(page.getByRole('button', { name: /Activer la 2FA/i })).toBeVisible()
  }
  else {
    await expect(page.getByText(/2FA active|active/i).first()).toBeVisible()
  }

  expect(errors).toEqual([])
})
