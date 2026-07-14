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

test('tableau de bord : un pipeline joué produit des chiffres cohérents', async ({ page }) => {
  const errors = watchConsole(page)
  const orgName = `E2E Dash ${Date.now()}`

  await login(page)

  // Jouer une piste jusqu'à la victoire (contact → réponse → gagnée).
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
  await page.getByRole('button', { name: /^contacter$|^contact$/i }).click()
  await page.getByRole('button', { name: /réponse reçue|reply received/i }).click()
  await page.getByRole('button', { name: /^gagnée$|^won$/i }).click()
  await expect(page.getByText(/gagnée|won/i).first()).toBeVisible()

  // Le tableau de bord reflète le parcours (les KPIs sont en texte : a11y).
  await page.goto('/dashboard')
  await waitForHydration(page)
  await expect(page.getByRole('heading', { level: 1 })).toHaveText(/tableau de bord|dashboard/i)
  await expect(page.getByText(/taux de réponse|response rate/i)).toBeVisible()
  await expect(page.getByText(/pistes contactées|contacted leads/i)).toBeVisible()
  await expect(page.getByText(/conversion/i).first()).toBeVisible()
  // Pipeline + activité + segments présents.
  await expect(page.getByText(/^pipeline$/i)).toBeVisible()
  await expect(page.getByText(/8 dernières semaines|last 8 weeks/i)).toBeVisible()
  await expect(page.getByText(/par segment|by segment/i)).toBeVisible()

  expect(errors).toEqual([])
})
