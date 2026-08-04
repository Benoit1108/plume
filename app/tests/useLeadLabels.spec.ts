import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed } from 'vue'

let profileData: { pipelineLabels?: Record<string, string> } | undefined

vi.stubGlobal('computed', computed)
vi.stubGlobal('useI18n', () => ({
  t: (key: string, fallback?: string) => (key === 'pipeline.statuses.WON' ? 'Gagnée' : fallback ?? key),
}))
vi.stubGlobal('useProfile', () => ({ get: () => Promise.resolve(profileData) }))
vi.stubGlobal('useQuery', () => ({ data: { get value() { return profileData } } }))
vi.stubGlobal('queryKeys', { profile: ['profile'] })

const { useLeadLabels, LEAD_PRIORITIES, LEAD_SOURCES } = await import('../composables/lead/useLeadLabels')

describe('useLeadLabels', () => {
  beforeEach(() => {
    profileData = undefined
  })

  it('traduit un statut connu et retombe sur la valeur brute sinon', () => {
    const { statusLabel } = useLeadLabels()

    expect(statusLabel('WON')).toBe('Gagnée')
    expect(statusLabel('INCONNU')).toBe('INCONNU')
  })

  it('utilise le libellé personnalisé du profil quand il existe (ADR-0031)', () => {
    profileData = { pipelineLabels: { WON: 'Signée' } }
    const { statusLabel } = useLeadLabels()

    expect(statusLabel('WON')).toBe('Signée') // override
    expect(statusLabel('LOST')).toBe('LOST') // pas d'override → i18n (ici : fallback = statut brut)
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
