/**
 * Wrapper minimal autour de $fetch, pointant sur l'API Symfony.
 * TODO M1 : injection du JWT (access token) + refresh automatique.
 */
export function useApi() {
  const config = useRuntimeConfig()

  return $fetch.create({
    baseURL: config.public.apiBase,
    headers: {
      Accept: 'application/ld+json',
    },
  })
}
