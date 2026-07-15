import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

test('boîte email : connexion OAuth (factice) depuis les réglages, état, déconnexion', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await page.goto('/settings')
  await waitForHydration(page)

  // État initial agnostique (tenant e2e partagé) : si une boîte est déjà
  // connectée par un autre parcours, on la déconnecte d'abord.
  const connectButton = page.getByRole('button', { name: /connecter gmail|connect gmail/i })
  const connectedBadge = page.getByText(/^connectée$|^connected$/i)
  await expect(connectButton.or(connectedBadge).first()).toBeVisible()
  if (await connectedBadge.isVisible()) {
    await page.getByRole('button', { name: /déconnecter|disconnect/i }).click()
    await page.getByRole('button', { name: /^déconnecter$|^disconnect$/i }).last().click()
    await expect(page.getByText(/boîte déconnectée|mailbox disconnected/i).first()).toBeVisible()
  }
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

test('boîte email : connexion Outlook (factice) enregistre le fournisseur OUTLOOK', async ({ page }) => {
  const errors = watchConsole(page)

  await login(page)
  await page.goto('/settings')
  await waitForHydration(page)

  // État agnostique (tenant e2e partagé) : déconnecter d'abord si déjà connecté.
  const connectGmail = page.getByRole('button', { name: /connecter gmail|connect gmail/i })
  const connectedBadge = page.getByText(/^connectée$|^connected$/i)
  await expect(connectGmail.or(connectedBadge).first()).toBeVisible()
  if (await connectedBadge.isVisible()) {
    await page.getByRole('button', { name: /déconnecter|disconnect/i }).click()
    await page.getByRole('button', { name: /^déconnecter$|^disconnect$/i }).last().click()
    await expect(page.getByText(/boîte déconnectée|mailbox disconnected/i).first()).toBeVisible()
  }

  // Consentement Outlook factice → callback → connectée. Le provider affiché est OUTLOOK.
  await page.getByRole('button', { name: /connecter outlook|connect outlook/i }).click()
  await page.waitForURL('**/settings')
  await expect(page.getByText(/boîte connectée|mailbox connected/i).first()).toBeVisible()
  await expect(page.getByText('OUTLOOK')).toBeVisible()

  // Nettoyage : déconnecter pour laisser le tenant e2e propre.
  await page.getByRole('button', { name: /déconnecter|disconnect/i }).click()
  await page.getByRole('button', { name: /^déconnecter$|^disconnect$/i }).last().click()
  await expect(page.getByText(/boîte déconnectée|mailbox disconnected/i).first()).toBeVisible()

  expect(errors).toEqual([])
})
