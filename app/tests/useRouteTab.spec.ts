import { beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, reactive, toValue } from 'vue'

const route = reactive({ query: {} as Record<string, string> })
const replace = vi.fn()

vi.stubGlobal('computed', computed)
vi.stubGlobal('toValue', toValue)
vi.stubGlobal('useRoute', () => route)
vi.stubGlobal('useRouter', () => ({ replace }))

const { useRouteTab } = await import('../composables/core/useRouteTab')

const TABS = ['profile', 'mailbox', 'sources'] as const

describe('useRouteTab', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    route.query = {}
  })

  it('sans paramètre, ouvre le premier onglet', () => {
    expect(useRouteTab(TABS).value).toBe('profile')
  })

  it('lit l\'onglet dans l\'URL (lien entrant, rechargement)', () => {
    route.query = { tab: 'mailbox' }
    expect(useRouteTab(TABS).value).toBe('mailbox')
  })

  it('ignore un onglet inconnu plutôt que d\'afficher du vide', () => {
    route.query = { tab: 'billing' }
    expect(useRouteTab(TABS).value).toBe('profile')
  })

  it('écrit l\'onglet dans l\'URL sans empiler d\'historique, en gardant les autres paramètres', () => {
    route.query = { from: 'onboarding' }
    const tab = useRouteTab(TABS)

    tab.value = 'sources'

    expect(replace).toHaveBeenCalledWith({ query: { from: 'onboarding', tab: 'sources' } })
  })

  it('suit une liste d\'onglets réactive (onglet conditionnel)', () => {
    route.query = { tab: 'billing' }
    const available = ['profile', 'billing']
    const tab = useRouteTab(() => available)

    expect(tab.value).toBe('billing')
  })

  it('accepte une clé de paramètre personnalisée', () => {
    route.query = { section: 'sources' }
    expect(useRouteTab(TABS, 'section').value).toBe('sources')
  })
})
