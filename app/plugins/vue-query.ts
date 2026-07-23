import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'

/**
 * TanStack Query (chantier 3, lot D) : cache + invalidation de l'état serveur, en remplacement
 * des `useAsyncData` + `refresh()` manuels. En SPA (ssr:false) le cache est purement client —
 * pas de déshydratation/hydratation SSR à gérer.
 *
 * `retry: false` : le rejeu sur 401 (refresh du token) est déjà géré DANS `useApi` (mutex) ; on
 * ne veut pas de rejeu aveugle de TanStack par-dessus. `staleTime` modéré : évite les refetch
 * redondants entre navigations rapprochées ; l'invalidation explicite (après mutation) prime.
 */
export default defineNuxtPlugin((nuxtApp) => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        staleTime: 30_000,
        retry: false,
        refetchOnWindowFocus: false,
      },
    },
  })
  nuxtApp.vueApp.use(VueQueryPlugin, { queryClient })
})
