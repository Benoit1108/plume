import type { Today } from '~/types/leads'

/** L'écran « Aujourd'hui » (relances dues, à contacter, progression hebdo). */
export function useToday() {
  const api = useApi()

  return {
    get: () => api<Today>('/api/v1/today', { headers: { Accept: 'application/ld+json' } }),
  }
}
