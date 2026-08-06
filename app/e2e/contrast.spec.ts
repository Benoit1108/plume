import { expect, test } from '@playwright/test'
import { login, waitForHydration } from './helpers'

/**
 * Contrastes de texte (WCAG 1.4.3 — AA). Test dédié plutôt que la règle `color-contrast` d'axe :
 *  - nos fonds sont souvent SEMI-TRANSPARENTS (`bg-muted/40`, `bg-elevated/40`) → il faut empiler
 *    les couches jusqu'à une couche opaque pour connaître le fond effectif ;
 *  - les tokens Nuxt UI sont en `oklch()` → toute conversion maison est une source d'erreur (au
 *    premier essai, les trois composantes oklch ont été lues comme r,g,b : ratios absurdes). On
 *    laisse donc le NAVIGATEUR convertir, via un canvas 1×1.
 *
 * Ce test aurait attrapé les quatre régressions trouvées manuellement : text-dimmed à 2,39,
 * text-muted à 4,40, primary à 4,35 et les badges de statut (« Réponse reçue ») à 1,85.
 */
const PAGES = ['/', '/login', '/register'] as const
const THEMES = ['light', 'dark'] as const

/** Écrans de travail (derrière l'authentification) — les plus regardés, donc les plus coûteux à rater. */
const AUTHENTICATED_PAGES = [
  '/today', '/dashboard', '/leads', '/candidates', '/organizations', '/templates',
  '/settings?tab=profile', '/settings?tab=prospecting', '/settings?tab=notifications',
  '/settings?tab=mailbox', '/settings?tab=sources',
  '/account?tab=profile', '/account?tab=security', '/account?tab=data',
] as const

/** Nombre minimal d'éléments textuels attendus : garde contre un « 0 échec » obtenu sur une page vide. */
const MIN_INSPECTED: Record<string, number> = { '/': 40, '/login': 5, '/register': 5 }

interface Failure {
  ratio: number
  required: number
  fontSize: number
  color: string
  sample: string
}

const MEASURE = (): { failures: Failure[], inspected: number } => {
  const canvas = document.createElement('canvas')
  canvas.width = canvas.height = 1
  const cx = canvas.getContext('2d', { willReadFrequently: true })!

  const parse = (value: string): { r: number, g: number, b: number, a: number } | null => {
    if (!value || 'transparent' === value || 'none' === value) return null
    cx.clearRect(0, 0, 1, 1)
    cx.fillStyle = '#000'
    cx.fillStyle = value // le navigateur normalise rgb()/oklch()/color-mix()
    cx.fillRect(0, 0, 1, 1)
    const d = cx.getImageData(0, 0, 1, 1).data
    return { r: d[0]!, g: d[1]!, b: d[2]!, a: d[3]! / 255 }
  }
  const luminance = ({ r, g, b }: { r: number, g: number, b: number }): number => {
    const [rl, gl, bl] = [r, g, b].map((x) => {
      const c = x / 255
      return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
    }) as [number, number, number]
    return 0.2126 * rl + 0.7152 * gl + 0.0722 * bl
  }
  const flatten = (fg: { r: number, g: number, b: number, a: number }, bg: { r: number, g: number, b: number }) => ({
    r: fg.r * fg.a + bg.r * (1 - fg.a),
    g: fg.g * fg.a + bg.g * (1 - fg.a),
    b: fg.b * fg.a + bg.b * (1 - fg.a),
    a: 1,
  })
  const effectiveBackground = (el: Element) => {
    const layers: Array<{ r: number, g: number, b: number, a: number }> = []
    let node: Element | null = el
    while (node) {
      const bg = parse(getComputedStyle(node).backgroundColor)
      if (bg && bg.a > 0) {
        layers.push(bg)
        if (1 === bg.a) break
      }
      node = node.parentElement
    }
    let base = { r: 255, g: 255, b: 255, a: 1 }
    for (let i = layers.length - 1; i >= 0; i--) base = flatten(layers[i]!, base)
    return base
  }
  const contrast = (a: { r: number, g: number, b: number }, b: { r: number, g: number, b: number }): number => {
    const [l1, l2] = [luminance(a), luminance(b)]
    const [hi, lo] = l1 > l2 ? [l1, l2] : [l2, l1]
    return (hi + 0.05) / (lo + 0.05)
  }

  const failures: Failure[] = []
  const seen = new Set<string>()
  for (const el of document.querySelectorAll('p, span, h1, h2, h3, h4, li, a, button, div, label')) {
    const text = (el.textContent ?? '').trim()
    if (!text || el.children.length > 0) continue // feuilles textuelles uniquement
    const cs = getComputedStyle(el)
    if ('hidden' === cs.visibility || 'none' === cs.display) continue
    const rect = el.getBoundingClientRect()
    if (rect.width < 2 || rect.height < 2) continue // ignore les éléments sr-only
    const fg = parse(cs.color)
    if (!fg) continue

    const bg = effectiveBackground(el)
    const ratio = contrast(flatten(fg, bg), bg)
    const fontSize = Number.parseFloat(cs.fontSize)
    const bold = Number.parseInt(cs.fontWeight, 10) >= 700
    // « Grand texte » (1.4.3) : >= 24px, ou >= 18.66px en gras → 3:1 suffit.
    const required = (fontSize >= 24 || (fontSize >= 18.66 && bold)) ? 3 : 4.5

    const key = `${cs.color}|${Math.round(fontSize)}|${text.slice(0, 20)}`
    if (seen.has(key)) continue
    seen.add(key)

    if (ratio < required) {
      failures.push({ ratio: Number(ratio.toFixed(2)), required, fontSize, color: cs.color, sample: text.slice(0, 40) })
    }
  }
  return { failures: failures.sort((a, b) => a.ratio - b.ratio), inspected: seen.size }
}

for (const theme of THEMES) {
  for (const path of PAGES) {
    test(`contrastes de ${path} en thème ${theme}`, async ({ page }) => {
      await page.addInitScript(t => localStorage.setItem('nuxt-color-mode', t), theme)
      await page.emulateMedia({ reducedMotion: 'reduce' })
      await page.goto(path)
      await waitForHydration(page)
      await expect
        .poll(() => page.evaluate(() => document.documentElement.classList.contains('dark')))
        .toBe('dark' === theme)

      const { failures, inspected } = await page.evaluate(MEASURE)

      // Sans cette garde, une page qui ne charge pas donnerait « 0 échec » — faux négatif rencontré
      // pendant la revue (404 servi à la place de la page, puis thème non appliqué).
      expect(inspected).toBeGreaterThanOrEqual(MIN_INSPECTED[path] ?? 5)
      expect(failures.map(f => `${f.ratio}:1 < ${f.required}:1 — ${f.fontSize}px « ${f.sample} »`)).toEqual([])
    })
  }

  // L'APPLICATION, pas seulement la vitrine (revue TEST-P2a) : la revue du 2026-08-06 a mesuré ici
  // deux échecs (bouton d'onboarding 4,18 ; badge « Cet appareil » 4,30) qu'aucun garde-fou ne
  // voyait. Un seul test par thème (une session, N navigations) pour ne pas alourdir la CI.
  test(`contrastes des pages authentifiées en thème ${theme}`, async ({ page }) => {
    test.setTimeout(180_000)
    await page.addInitScript(t => localStorage.setItem('nuxt-color-mode', t), theme)
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await login(page)

    const problems: string[] = []
    for (const path of AUTHENTICATED_PAGES) {
      await page.goto(path)
      await waitForHydration(page)
      // Attendre le RENDU des données : mesurer des squelettes donnerait un faux « tout va bien ».
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible()
      await page.waitForLoadState('networkidle')
      const { failures, inspected } = await page.evaluate(MEASURE)
      expect(inspected, `page vide ou non chargée : ${path}`).toBeGreaterThanOrEqual(10)
      problems.push(...failures.map(f => `${path} — ${f.ratio}:1 < ${f.required}:1 (${f.fontSize}px) « ${f.sample} »`))
    }

    expect(problems).toEqual([])
  })
}
