import { describe, expect, it, vi } from 'vitest'
import { computed } from 'vue'

vi.stubGlobal('computed', computed)
vi.stubGlobal('useI18n', () => ({
  t: (key: string, fallback?: string) => (key === 'pipeline.statuses.WON' ? 'Gagnée' : fallback ?? key),
}))

const { useLeadLabels, LEAD_PRIORITIES, LEAD_SOURCES } = await import('../composables/useLeadLabels')

describe('useLeadLabels', () => {
  it('traduit un statut connu et retombe sur la valeur brute sinon', () => {
    const { statusLabel } = useLeadLabels()

    expect(statusLabel('WON')).toBe('Gagnée')
    expect(statusLabel('INCONNU')).toBe('INCONNU')
  })

  it('formate la paire de langues pour l\'affichage', () => {
    const { pairLabel } = useLeadLabels()

    expect(pairLabel('en>fr')).toBe('en → fr')
  })

  it('expose des options alignées sur les enums du domaine', () => {
    const { priorityOptions, sourceOptions } = useLeadLabels()

    expect(priorityOptions.value.map(o => o.value)).toEqual([...LEAD_PRIORITIES])
    expect(sourceOptions.value.map(o => o.value)).toEqual([...LEAD_SOURCES])
  })
})
