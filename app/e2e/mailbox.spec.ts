import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

test('boîte email : connexion OAuth (factice) depuis les réglages, état, déconnexion', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await page.goto('/settings')
  await waitForHydration(page)

  // Déconnectée (ou jamais connectée) : le bouton Connecter Gmail est là.
  const connectButton = page.getByRole('button', { name: /connecter gmail|connect gmail/i })
  await expect(connectButton).toBeVisible()

  // Le consentement factice redirige immédiatement vers notre callback → connectée.
  await connectButton.click()
  await page.waitForURL('**/settings')
  await expect(page.getByText(/boîte connectée|mailbox connected/i).first()).toBeVisible()
  await expect(page.getByText('traductrice@gmail.example')).toBeVisible()
  await expect(page.getByText(/^connectée$|^connected$/i)).toBeVisible()

  // Déconnexion (confirmée) : les jetons sont effacés côté serveur.
  await page.getByRole('button', { name: /déconnecter|disconnect/i }).click()
  await page.getByRole('button', { name: /^déconnecter$|^disconnect$/i }).last().click()
  await expect(page.getByText(/boîte déconnectée|mailbox disconnected/i).first()).toBeVisible()
  await expect(page.getByRole('button', { name: /connecter gmail|connect gmail/i })).toBeVisible()

  expect(errors).toEqual([])
})
