import { expect, test } from '@playwright/test'
import { createLeadViaUi, login, waitForHydration, watchConsole } from './helpers'

test('parcours quotidien : à contacter → contact → relance replanifiée → relance faite', async ({ page }) => {
  const errors = watchConsole(page)
  const orgName = `E2E Aujourdhui ${Date.now()}`

  await login(page)

  await createLeadViaUi(page, orgName)
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
