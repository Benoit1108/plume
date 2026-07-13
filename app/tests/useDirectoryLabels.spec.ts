import { describe, expect, it, vi } from 'vitest'
import { computed } from 'vue'

vi.stubGlobal('computed', computed)
vi.stubGlobal('useI18n', () => ({
  t: (key: string, fallback?: string) => (key === 'directory.types.PUBLISHER' ? 'Éditeur' : fallback ?? key),
}))

const { useDirectoryLabels, ORGANIZATION_TYPES, SEGMENTS } = await import('../composables/useDirectoryLabels')

describe('useDirectoryLabels', () => {
  it('traduit un type connu et retombe sur la valeur brute sinon', () => {
    const { typeLabel } = useDirectoryLabels()

    expect(typeLabel('PUBLISHER')).toBe('Éditeur')
    expect(typeLabel('INCONNU')).toBe('INCONNU')
  })

  it('expose des options alignées sur les enums du domaine', () => {
    const { typeOptions, segmentOptions } = useDirectoryLabels()

    expect(typeOptions.value.map(o => o.value)).toEqual([...ORGANIZATION_TYPES])
    expect(segmentOptions.value.map(o => o.value)).toEqual([...SEGMENTS])
  })
})
