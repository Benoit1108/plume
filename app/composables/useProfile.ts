import type { Profile } from '~/types/leads'

/** Profil (objectif hebdomadaire, fuseau). */
export function useProfile() {
  const api = useApi()

  return {
    get: () => api<Profile>('/api/v1/profile', { headers: { Accept: 'application/ld+json' } }),
    updateWeeklyGoal: (weeklyGoal: number) =>
      api<Profile>('/api/v1/profile', { method: 'PATCH', body: { weeklyGoal }, headers: { 'Content-Type': 'application/merge-patch+json' } }),
  }
}
