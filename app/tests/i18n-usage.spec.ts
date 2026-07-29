import { globSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'
import fr from '../i18n/locales/fr.json'

/**
 * Garde-fou (revue globale front P1) : toute clé i18n STATIQUE utilisée dans le code doit être
 * déclarée. Le test de parité fr/en ne détecte que les asymétries entre locales, PAS une clé
 * utilisée mais jamais déclarée (cas `account.twoFactor.copied` → toast littéral affiché).
 */

function flatten(obj: Record<string, unknown>, prefix = ''): Set<string> {
  const keys = new Set<string>()
  for (const [k, v] of Object.entries(obj)) {
    const full = prefix ? `${prefix}.${k}` : k
    if (v !== null && typeof v === 'object') for (const nested of flatten(v as Record<string, unknown>, full)) keys.add(nested)
    else keys.add(full)
  }
  return keys
}

// Préfixes de clés construites DYNAMIQUEMENT (template literals) — exemptés de la vérification.
const DYNAMIC_PREFIXES = [
  'onboarding.steps.', 'onboarding.actions.', 'notifications.types.',
  'directory.types.', 'directory.segments.', 'mailbox.statuses.', 'mailbox.failures.',
  'drafts.failures.', 'pipeline.statuses.', 'pipeline.actions.', 'draft.types.', 'segments.',
]

describe('clés i18n utilisées', () => {
  it('sont toutes déclarées dans fr.json (hors clés dynamiques)', () => {
    const declared = flatten(fr as Record<string, unknown>)
    const root = fileURLToPath(new URL('..', import.meta.url))
    const files = globSync('{pages,components,composables,layouts,utils,middleware,stores}/**/*.{vue,ts}', { cwd: root })

    const missing = new Set<string>()
    const keyCall = /(?:\$t|[^\w.]t)\(\s*'([a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)+)'/g

    for (const file of files) {
      const src = readFileSync(`${root}/${file}`, 'utf8')
      for (const match of src.matchAll(keyCall)) {
        const key = match[1]!
        if (declared.has(key)) continue
        if (DYNAMIC_PREFIXES.some(p => key.startsWith(p))) continue
        missing.add(`${key}  (${file})`)
      }
    }

    expect([...missing].sort()).toEqual([])
  })
})
