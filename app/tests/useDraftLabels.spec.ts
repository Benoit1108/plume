import { describe, expect, it, vi } from 'vitest'
import { computed } from 'vue'

vi.stubGlobal('computed', computed)
vi.stubGlobal('useI18n', () => ({
  t: (key: string, fallback?: string) => {
    const known: Record<string, string> = {
      'drafts.types.APPLICATION_EMAIL': 'Mail de candidature',
      'drafts.statuses.READY': 'Prêt à relire',
      'drafts.failures.generation_failed': 'La génération a échoué.',
      'drafts.failures.contact_not_allowed': 'Ne plus contacter.',
    }
    return known[key] ?? fallback ?? key
  },
}))

const { useDraftLabels, DRAFT_TYPES } = await import('../composables/useDraftLabels')

describe('useDraftLabels', () => {
  it('traduit types et statuts, retombe sur la clé inconnue', () => {
    const labels = useDraftLabels()
    expect(labels.typeLabel('APPLICATION_EMAIL')).toBe('Mail de candidature')
    expect(labels.statusLabel('READY')).toBe('Prêt à relire')
    expect(labels.typeLabel('UNKNOWN')).toBe('UNKNOWN')
  })

  it('failureLabel retombe sur generation_failed pour un code inconnu', () => {
    const labels = useDraftLabels()
    expect(labels.failureLabel('contact_not_allowed')).toBe('Ne plus contacter.')
    expect(labels.failureLabel('mystery_code')).toBe('La génération a échoué.')
  })

  it('expose les options de type et la couleur de statut', () => {
    const labels = useDraftLabels()
    expect(labels.typeOptions.value).toHaveLength(DRAFT_TYPES.length)
    expect(labels.statusColor('GENERATING')).toBe('warning')
    expect(labels.statusColor('READY')).toBe('success')
    expect(labels.statusColor('FAILED')).toBe('error')
  })
})
