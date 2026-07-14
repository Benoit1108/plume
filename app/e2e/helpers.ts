import type { Page } from '@playwright/test'
import { expect } from '@playwright/test'

export const E2E_EMAIL = 'e2e@plume.test'
export const E2E_PASSWORD = 'e2e-secret-123'

/** Garde-fou : toute erreur console / warning d'hydratation fait échouer le test. */
export function watchConsole(page: Page): string[] {
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

/** N'interagir qu'après l'hydratation (le POST natif pré-hydratation a déjà mordu). */
export async function waitForHydration(page: Page): Promise<void> {
  await page.waitForFunction(() => {
    const root = document.querySelector('#__nuxt')
    return root !== null && '__vue_app__' in root
  })
}

/** Connexion : l'accueil est « Aujourd'hui » depuis M1.3. */
export async function login(page: Page): Promise<void> {
  await page.goto('/login')
  await waitForHydration(page)
  await page.getByRole('textbox').first().fill(E2E_EMAIL)
  await page.locator('input[type="password"]').fill(E2E_PASSWORD)
  await page.getByRole('button', { name: /se connecter|sign in/i }).click()
  await page.waitForURL('**/today')
}

/** Crée une organisation puis une piste depuis sa fiche ; laisse la page sur la fiche piste. */
export async function createLeadViaUi(page: Page, orgName: string): Promise<void> {
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
}

/** Un toast (le titre apparaît aussi en région aria-live : viser le premier). */
export async function expectToast(page: Page, pattern: RegExp): Promise<void> {
  await expect(page.getByText(pattern).first()).toBeVisible()
}
