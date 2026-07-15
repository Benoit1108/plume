import type { Profile } from '~/types/leads'

/** Profil (objectif hebdomadaire, fuseau, présentation pour la rédaction assistée). */
export function useProfile() {
  const api = useApi()
  const patch = { 'Content-Type': 'application/merge-patch+json' }

  return {
    get: () => api<Profile>('/api/v1/profile', { headers: { Accept: 'application/ld+json' } }),
    updateWeeklyGoal: (weeklyGoal: number) =>
      api<Profile>('/api/v1/profile', { method: 'PATCH', body: { weeklyGoal }, headers: patch }),
    update: (data: Partial<Pick<Profile, 'weeklyGoal' | 'bio' | 'specialties' | 'signature' | 'firstName' | 'lastName'>>) =>
      api<Profile>('/api/v1/profile', { method: 'PATCH', body: data, headers: patch }),
  }
}
