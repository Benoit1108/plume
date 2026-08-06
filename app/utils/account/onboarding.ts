import type { Profile } from '~/types/domain/leads'
import type { MailboxStatus } from '~/types/domain/mailbox'

/**
 * Onboarding (V2.1) : logique PURE de la checklist « Bien démarrer » affichée sur Aujourd'hui.
 * Les étapes sont DÉRIVÉES des données réelles (pas d'état serveur dédié) ; la carte disparaît
 * d'elle-même quand tout est fait (ou masquée à la demande), mémorisé en localStorage par compte.
 */

export type OnboardingStepId = 'presentation' | 'mailbox' | 'feed' | 'directory'

export interface OnboardingStep {
  id: OnboardingStepId
  done: boolean
  /** Cible du lien d'action (toutes les étapes se règlent en un clic). */
  to: string
}

export interface OnboardingInputs {
  profile: Pick<Profile, 'bio' | 'specialties' | 'signature'> | null
  mailboxStatus: MailboxStatus | null
  feedCount: number
  organizationCount: number
}

/** La présentation nourrit les brouillons IA : « faite » dès qu'un des trois champs est rempli. */
function hasPresentation(profile: OnboardingInputs['profile']): boolean {
  if (!profile) return false
  return [profile.bio, profile.specialties, profile.signature]
    .some(value => typeof value === 'string' && value.trim() !== '')
}

export function computeOnboardingSteps(inputs: OnboardingInputs): OnboardingStep[] {
  return [
    // Réglages est à onglets : chaque étape vise le sien, sinon on retombe sur « Profil ».
    { id: 'presentation', done: hasPresentation(inputs.profile), to: '/settings?tab=profile' },
    { id: 'mailbox', done: inputs.mailboxStatus === 'CONNECTED', to: '/settings?tab=mailbox' },
    { id: 'feed', done: inputs.feedCount > 0, to: '/settings?tab=sources' },
    { id: 'directory', done: inputs.organizationCount > 0, to: '/organizations' },
  ]
}

export function isOnboardingComplete(steps: OnboardingStep[]): boolean {
  return steps.every(step => step.done)
}

/** Clé localStorage PAR COMPTE (un navigateur peut héberger plusieurs comptes). */
export function onboardingStorageKey(email: string): string {
  return `plume_onboarding_done:${email}`
}
