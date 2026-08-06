import { describe, expect, it, vi } from 'vitest'
import { computed, ref, toValue } from 'vue'

vi.stubGlobal('ref', ref)
vi.stubGlobal('computed', computed)
vi.stubGlobal('toValue', toValue)

const { useShowMore } = await import('../composables/core/useShowMore')

describe('useShowMore', () => {
  it('plafonne la liste et annonce combien d\'éléments restent', () => {
    const { visible, hiddenCount, expanded } = useShowMore(['a', 'b', 'c', 'd', 'e', 'f', 'g'], 5)

    expect(expanded.value).toBe(false)
    expect(visible.value).toEqual(['a', 'b', 'c', 'd', 'e'])
    expect(hiddenCount.value).toBe(2)
  })

  it('déplie puis replie', () => {
    const { visible, toggle, expanded } = useShowMore(['a', 'b', 'c'], 2)

    toggle()
    expect(expanded.value).toBe(true)
    expect(visible.value).toEqual(['a', 'b', 'c'])

    toggle()
    expect(visible.value).toEqual(['a', 'b'])
  })

  it('ne masque rien quand la liste tient sous le plafond', () => {
    const { visible, hiddenCount } = useShowMore(['a', 'b'], 5)

    expect(visible.value).toEqual(['a', 'b'])
    expect(hiddenCount.value).toBe(0)
  })

  it('suit une source réactive', () => {
    const items = ref(['a', 'b'])
    const { visible, hiddenCount } = useShowMore(items, 2)

    items.value = ['a', 'b', 'c', 'd']
    expect(visible.value).toEqual(['a', 'b'])
    expect(hiddenCount.value).toBe(2)
  })

  it('traite un plafond négatif comme zéro (tout masqué, rien de cassé)', () => {
    const { visible, hiddenCount } = useShowMore(['a', 'b'], -3)

    expect(visible.value).toEqual([])
    expect(hiddenCount.value).toBe(2)
  })
})
