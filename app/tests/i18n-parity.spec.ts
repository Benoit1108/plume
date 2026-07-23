import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

/**
 * Parité des locales (chantier 3, lot B) : `fr.json` et `en.json` doivent avoir EXACTEMENT le
 * même ensemble de clés (récursif). Empêche qu'une clé ajoutée d'un seul côté passe en prod
 * (le fallback masquerait la traduction manquante).
 */
function keyPaths(obj: Record<string, unknown>, prefix = ''): string[] {
  const paths: string[] = []
  for (const [key, value] of Object.entries(obj)) {
    const path = prefix ? `${prefix}.${key}` : key
    if (null !== value && 'object' === typeof value && !Array.isArray(value)) {
      paths.push(...keyPaths(value as Record<string, unknown>, path))
    }
    else {
      paths.push(path)
    }
  }
  return paths.sort()
}

function locale(name: string): Record<string, unknown> {
  return JSON.parse(readFileSync(new URL(`../i18n/locales/${name}.json`, import.meta.url), 'utf8')) as Record<string, unknown>
}

describe('parité i18n fr/en', () => {
  const frKeys = keyPaths(locale('fr'))
  const enKeys = keyPaths(locale('en'))

  it('en ne manque aucune clé présente en fr', () => {
    expect(frKeys.filter(k => !enKeys.includes(k))).toEqual([])
  })

  it('fr ne manque aucune clé présente en en', () => {
    expect(enKeys.filter(k => !frKeys.includes(k))).toEqual([])
  })
})
