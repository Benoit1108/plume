import { globSync, readFileSync } from 'node:fs'
import { fileURLToPath } from 'node:url'
import { describe, expect, it } from 'vitest'

/**
 * Garde-fou : une page doit avoir UN SEUL nœud racine dans son template.
 *
 * Un commentaire placé au-dessus de l'élément racine en est un deuxième. Nuxt refuse alors
 * d'animer la transition de page — et la page suivante s'affiche VIDE au changement de route
 * (`pages/candidates.vue` : « aucune donnée ne s'affiche »). Le symptôme est spectaculaire, la
 * cause invisible à la relecture, et la seule trace est un avertissement en console.
 */
describe('templates de pages', () => {
  it('n\'ont qu\'un seul nœud racine (pas de commentaire au-dessus de la racine)', () => {
    const root = fileURLToPath(new URL('..', import.meta.url))
    const offenders: string[] = []

    for (const file of globSync('pages/**/*.vue', { cwd: root })) {
      const source = readFileSync(`${root}/${file}`, 'utf8')
      const start = source.indexOf('<template>')
      if (start === -1) continue

      const body = source.slice(start + '<template>'.length)
      const firstMeaningful = body.split('\n').map(line => line.trim()).find(line => line !== '')
      if (firstMeaningful?.startsWith('<!--')) offenders.push(file)
    }

    expect(offenders).toEqual([])
  })
})
