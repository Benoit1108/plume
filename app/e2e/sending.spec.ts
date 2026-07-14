import { expect, test } from '@playwright/test'
import { login, waitForHydration, watchConsole } from './helpers'

test('envoi : boîte connectée → brouillon relu → Envoyer → journal + piste avancée (D3)', async ({ page }) => {
  const errors = watchConsole(page)
  const orgName = `E2E Envoi ${Date.now()}`

  await login(page)

  // Boîte connectée (consentement factice, redirection immédiate).
  await page.goto('/settings')
  await waitForHydration(page)
  const connectButton = page.getByRole('button', { name: /connecter gmail|connect gmail/i })
  const connectedBadge = page.getByText(/^connectée$|^connected$/i)
  // Attendre la fin du chargement de la section (bouton OU badge visible).
  await expect(connectButton.or(connectedBadge).first()).toBeVisible()
  if (await connectButton.isVisible()) {
    await connectButton.click()
    await page.waitForURL('**/settings')
  }
  await expect(connectedBadge).toBeVisible()

  // Organisation, puis CONTACT AVEC EMAIL (le destinataire), puis piste.
  await page.goto('/organizations/new')
  await waitForHydration(page)
  await page.getByRole('textbox').first().fill(orgName)
  await page.getByRole('button', { name: /créer|create/i }).click()
  await page.waitForURL(/\/organizations\/[0-9a-f-]+$/)
  await waitForHydration(page)
  await page.getByRole('button', { name: /^contact$/i }).click()
  await page.getByRole('textbox', { name: /^(nom complet|full name)\*?$/i }).fill('Jeanne Duval')
  await page.getByRole('textbox', { name: /^email$/i }).fill(`jeanne+${Date.now()}@editions.example`)
  await page.getByRole('button', { name: /^(ajouter|add)$/i }).click()
  await expect(page.getByText('Jeanne Duval')).toBeVisible()

  // Piste depuis la fiche organisation.
  await page.getByRole('link', { name: /créer une piste|create a lead/i }).click()
  await page.waitForURL(/\/leads\/new/)
  await waitForHydration(page)
  await page.getByRole('button', { name: /créer|create/i }).click()
  await page.waitForURL(/\/leads\/[0-9a-f-]+$/)
  const capturedLeadUrl = page.url()
  const leadUrl = (): string => capturedLeadUrl

  // Brouillon canned → READY → Envoyer (confirmation draft-first).
  await page.getByRole('button', { name: /générer un message|generate a message/i }).click()
  await page.getByRole('button', { name: /^générer$|^generate$/i }).click()
  await expect(page.getByText(/prêt à relire|ready to review/i).first()).toBeVisible({ timeout: 20_000 })
  await page.getByRole('button', { name: /mail de candidature|application email/i }).first().click()
  await page.getByRole('button', { name: /^envoyer$|^send$/i }).click()
  await page.getByRole('button', { name: /^envoyer$|^send$/i }).last().click()
  await expect(page.getByText(/envoi lancé|send started/i).first()).toBeVisible()

  // Journal : email envoyé + contact établi (D3). L'envoi et la politique
  // tournent dans le worker : on recharge la fiche jusqu'à voir les deux.
  await expect(async () => {
    await page.reload()
    await waitForHydration(page)
    await expect(page.getByText(/email envoyé|email sent/i).first()).toBeVisible()
    await expect(page.getByText(/contact établi|contact made/i).first()).toBeVisible()
  }).toPass({ timeout: 25_000 })

  // M2.3 — la boucle se ferme : relève manuelle → réponse captée (fetcher factice)
  // → piste en discussion, aperçu au journal.
  await page.goto('/settings')
  await waitForHydration(page)
  await page.getByRole('button', { name: /relever les réponses|fetch replies/i }).click()
  await expect(page.getByText(/relève effectuée|fetch done/i).first()).toBeVisible()

  await expect(async () => {
    await page.goto(leadUrl())
    await waitForHydration(page)
    await expect(page.getByText(/réponse reçue|reply received/i).first()).toBeVisible()
    await expect(page.getByText(/pouvez-vous nous envoyer vos références/i).first()).toBeVisible()
    await expect(page.getByText(/en discussion|in discussion/i).first()).toBeVisible()
  }).toPass({ timeout: 25_000 })

  expect(errors).toEqual([])
})
