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
  await page.waitForURL(/\/(today|organizations)/)
}

test('parcours quotidien : à contacter → contact → relance replanifiée → relance faite', async ({ page }) => {
  const errors = watchConsole(page)
  const orgName = `E2E Aujourdhui ${Date.now()}`

  await login(page)

  // Créer organisation + piste.
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
  const leadUrl = page.url()

  // « Aujourd'hui » : la piste attend dans À contacter — action rapide Contacter.
  await page.goto('/today')
  await waitForHydration(page)
  await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
  const toContactRow = page.locator('li', { hasText: orgName }).first()
  await expect(toContactRow).toBeVisible()
  await toContactRow.getByRole('button', { name: /^contacter$|^contact$/i }).click()
  await expect(page.locator('li', { hasText: orgName })).toHaveCount(0) // relance J+7 : pas due

  // Fiche : replanifier la relance à aujourd'hui.
  await page.goto(leadUrl)
  await waitForHydration(page)
  await page.getByRole('button', { name: /replanifier|reschedule/i }).click()
  await page.locator('input[type="date"]').fill(new Date().toISOString().slice(0, 10))
  await page.getByRole('button', { name: /^planifier$|^schedule$/i }).click()
  await expect(page.getByText(/prévue le|due on/i)).toBeVisible()

  // « Aujourd'hui » : la relance est due — action rapide Relance faite.
  await page.goto('/today')
  await waitForHydration(page)
  const dueRow = page.locator('li', { hasText: orgName }).first()
  await expect(dueRow).toBeVisible()
  await dueRow.getByRole('button', { name: /relance faite|follow-up done/i }).click()
  await expect(page.locator('li', { hasText: orgName })).toHaveCount(0) // suivante à J+21

  expect(errors).toEqual([])
})
