import { expect, test } from '@playwright/test'
import { login, waitForHydration } from './helpers'

/**
 * Garde-fou « utilisable au doigt » (revue TEST-P2b) : à 390 px, AUCUNE page ne doit faire défiler
 * le document horizontalement. Les listes larges (kanban, tableaux) défilent DANS leur conteneur —
 * jamais en emportant la navigation et l'en-tête hors de l'écran.
 *
 * Ce test aurait attrapé UX-P1 : le kanban clippait correctement, mais un libellé `sr-only`
 * (position absolue, sans ancêtre positionné) échappait au clip et étendait la zone de défilement
 * du document de 1 545 px. Invisible à la relecture, évident à la mesure.
 */
const PAGES = [
  '/today', '/dashboard', '/leads', '/candidates', '/organizations', '/templates',
  '/settings?tab=prospecting', '/settings?tab=notifications', '/account?tab=security',
  '/leads/new', '/organizations/new',
] as const

test('aucun défilement horizontal du document à 390 px', async ({ page }) => {
  test.setTimeout(180_000)
  await page.setViewportSize({ width: 390, height: 844 })
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await login(page)

  const overflowing: string[] = []
  for (const path of PAGES) {
    await page.goto(path)
    await waitForHydration(page)
    // Mesuré DEUX fois : pendant le chargement (les squelettes débordaient aussi) puis une fois
    // les données rendues — un défilement transitoire reste un défilement pour l'utilisatrice.
    const loading = await page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth)
    if (loading > 0) overflowing.push(`${path} pendant le chargement (+${loading}px)`)

    await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
    await page.waitForLoadState('networkidle')
    const overflow = await page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth)
    if (overflow > 0) overflowing.push(`${path} (+${overflow}px)`)
  }

  expect(overflowing).toEqual([])
})

test('la vitrine publique ne déborde pas non plus à 390 px', async ({ page }) => {
  await page.setViewportSize({ width: 390, height: 844 })
  await page.emulateMedia({ reducedMotion: 'reduce' })

  for (const path of ['/', '/login', '/register']) {
    await page.goto(path)
    await waitForHydration(page)
    const overflow = await page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth)
    // `<= 0` et non `=== 0` : la gouttière de défilement réservée (`scrollbar-gutter: stable`)
    // rend `clientWidth` plus PETIT que `scrollWidth` sur une page qui ne défile pas. Seul un
    // débordement POSITIF fait défiler la page latéralement.
    expect(overflow, `${path} déborde de ${overflow}px`).toBeLessThanOrEqual(0)
  }
})
