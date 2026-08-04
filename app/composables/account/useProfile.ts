import type { Profile } from '~/types/leads'

/** Profil (objectif hebdomadaire, fuseau, présentation pour la rédaction assistée). */
export function useProfile() {
  const api = useApi()
  const patch = { 'Content-Type': 'application/merge-patch+json' }

  return {
    get: () => api<Profile>('/api/v1/profile', { headers: { Accept: 'application/ld+json' } }),
    update: (data: Partial<Pick<Profile, 'weeklyGoal' | 'bio' | 'specialties' | 'signature' | 'firstName' | 'lastName' | 'digestFrequency' | 'followUpCadence' | 'pipelineLabels' | 'notificationPreferences' | 'dormantClientThresholdDays' | 'weeklyReportEnabled'>>) =>
      api<Profile>('/api/v1/profile', { method: 'PATCH', body: data, headers: patch }),
  }
}
