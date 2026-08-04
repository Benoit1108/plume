import { describe, expect, it } from 'vitest'
import { computeOnboardingSteps, isOnboardingComplete, onboardingStorageKey } from '../utils/account/onboarding'

const emptyInputs = {
  profile: null,
  mailboxStatus: null,
  feedCount: 0,
  organizationCount: 0,
} as const

describe('computeOnboardingSteps', () => {
  it('marque tout « à faire » pour un compte neuf', () => {
    const steps = computeOnboardingSteps({ ...emptyInputs })

    expect(steps.map(s => s.id)).toEqual(['presentation', 'mailbox', 'feed', 'directory'])
    expect(steps.every(s => !s.done)).toBe(true)
  })

  it('valide la présentation dès qu\'un des trois champs est rempli', () => {
    const bioOnly = computeOnboardingSteps({ ...emptyInputs, profile: { bio: 'Traductrice EN>FR', specialties: null, signature: null } })
    expect(bioOnly[0]!.done).toBe(true)

    const blank = computeOnboardingSteps({ ...emptyInputs, profile: { bio: '   ', specialties: '', signature: null } })
    expect(blank[0]!.done).toBe(false)
  })

  it('valide la boîte uniquement CONNECTED (pas ERROR ni REVOKED)', () => {
    expect(computeOnboardingSteps({ ...emptyInputs, mailboxStatus: 'CONNECTED' })[1]!.done).toBe(true)
    expect(computeOnboardingSteps({ ...emptyInputs, mailboxStatus: 'ERROR' })[1]!.done).toBe(false)
    expect(computeOnboardingSteps({ ...emptyInputs, mailboxStatus: 'NONE' })[1]!.done).toBe(false)
  })

  it('valide flux et répertoire au premier élément', () => {
    const steps = computeOnboardingSteps({ ...emptyInputs, feedCount: 1, organizationCount: 3 })
    expect(steps[2]!.done).toBe(true)
    expect(steps[3]!.done).toBe(true)
  })

  it('chaque étape pointe vers une page d\'action', () => {
    for (const step of computeOnboardingSteps({ ...emptyInputs })) {
      expect(step.to.startsWith('/')).toBe(true)
    }
  })
})

describe('isOnboardingComplete', () => {
  it('ne vaut vrai que si TOUTES les étapes sont faites', () => {
    const done = computeOnboardingSteps({
      profile: { bio: 'x', specialties: null, signature: null },
      mailboxStatus: 'CONNECTED',
      feedCount: 1,
      organizationCount: 1,
    })
    expect(isOnboardingComplete(done)).toBe(true)

    const partial = computeOnboardingSteps({ ...emptyInputs, feedCount: 1 })
    expect(isOnboardingComplete(partial)).toBe(false)
  })
})

describe('onboardingStorageKey', () => {
  it('est propre à chaque compte', () => {
    expect(onboardingStorageKey('a@plume.test')).not.toEqual(onboardingStorageKey('b@plume.test'))
  })
})
