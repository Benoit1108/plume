import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'
import { waitForHydration } from './helpers'

/**
 * Garde-fou d'accessibilité automatisé (revue « 10/10 »). La revue manuelle a trouvé sept défauts
 * BLOQUANTS sur le parcours public (contrastes, carrousel non pausable, cibles de 8 px, alertes non
 * annoncées) : ce fichier empêche leur retour, et celui de toute nouvelle violation WCAG A/AA
 * détectable automatiquement.
 *
 * `color-contrast` est délégué à contrast.spec.ts : axe échantillonne des pixels et se trompe sur nos
 * fonds semi-transparents (`bg-muted/40`), le canvas de particules en `fixed inset-0` et les blocs à
 * `opacity: 0` avant révélation. Le test dédié calcule le fond EFFECTIF, alpha compris.
 */
const PUBLIC_PAGES = ['/', '/login', '/register'] as const
const THEMES = ['light', 'dark'] as const

for (const theme of THEMES) {
  for (const path of PUBLIC_PAGES) {
    test(`accessibilité de ${path} en thème ${theme}`, async ({ page }) => {
      // Clé de stockage de @nuxtjs/color-mode (vérifié : localStorage, storageKey par défaut).
      await page.addInitScript(t => localStorage.setItem('nuxt-color-mode', t), theme)
      // Mouvement réduit : fige le carrousel et les révélations au scroll → DOM déterministe,
      // sinon axe scanne une cible mouvante et le test devient instable.
      await page.emulateMedia({ reducedMotion: 'reduce' })

      await page.goto(path)
      await waitForHydration(page)
      // On s'assure que le thème demandé est BIEN appliqué : sans cette garde, un scan « vert »
      // pourrait n'avoir testé que le thème par défaut (piège rencontré pendant la revue).
      await expect
        .poll(() => page.evaluate(() => document.documentElement.classList.contains('dark')))
        .toBe('dark' === theme)

      const { violations } = await new AxeBuilder({ page })
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
        .disableRules(['color-contrast'])
        .analyze()

      // Message lisible : l'identifiant de règle + le nombre de nœuds, pas un dump illisible.
      expect(violations.map(v => `${v.id} (${v.nodes.length})`)).toEqual([])
    })
  }
}

test('le carrousel est pausable et ne reprend pas la main (WCAG 2.2.2)', async ({ page }) => {
  const errors: string[] = []
  page.on('pageerror', e => errors.push(e.message))

  await page.goto('/')
  await waitForHydration(page)

  const tabs = page.getByRole('tab')
  await expect(tabs).toHaveCount(3)

  // Cible tactile : 24x24 px minimum (WCAG 2.5.8) — les pastilles faisaient 8 px.
  const box = await tabs.first().boundingBox()
  expect(box?.width ?? 0).toBeGreaterThanOrEqual(24)
  expect(box?.height ?? 0).toBeGreaterThanOrEqual(24)

  // Une sélection explicite doit TENIR : avant, le défilement auto reprenait la main après 3,6 s.
  await tabs.nth(1).click()
  await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true')
  await page.waitForTimeout(4500)
  await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true')

  // Navigation au clavier entre onglets.
  await tabs.nth(1).focus()
  await page.keyboard.press('ArrowRight')
  await expect(tabs.nth(2)).toHaveAttribute('aria-selected', 'true')

  // Les écrans inactifs sortent de l'arbre d'accessibilité (ils étaient lus tous les trois).
  const inert = await page.evaluate(() =>
    [...document.querySelectorAll('[role=tabpanel]')].map(el => el.hasAttribute('inert')),
  )
  expect(inert.filter(v => !v)).toHaveLength(1)

  // Un contrôle de pause existe (le survol souris ne compte pas : inatteignable au clavier).
  await expect(page.getByRole('button', { name: /pause|reprendre|resume/i })).toHaveCount(1)

  expect(errors).toEqual([])
})

test('la vitrine ne défile pas latéralement sur mobile', async ({ page }) => {
  // 390 px : la page débordait à 455 px (canvas non contraint + en-tête trop chargé).
  await page.setViewportSize({ width: 390, height: 844 })
  await page.goto('/')
  await waitForHydration(page)

  const { scrollWidth, clientWidth } = await page.evaluate(() => ({
    scrollWidth: document.documentElement.scrollWidth,
    clientWidth: document.documentElement.clientWidth,
  }))
  // Tolérance de 1 px : arrondis sous-pixel des bordures, sans défilement réel.
  expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1)
})
