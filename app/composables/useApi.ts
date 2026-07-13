/**
 * Client HTTP authentifié vers l'API Symfony.
 * Ajoute le Bearer token et tente UN refresh automatique sur 401.
 */
export function useApi() {
  const base = useRuntimeConfig().public.apiBase
  const auth = useAuthStore()

  async function api<T>(path: string, options: Record<string, unknown> = {}, retry = true): Promise<T> {
    try {
      return await $fetch<T>(path, {
        baseURL: base,
        ...options,
        headers: {
          Accept: 'application/ld+json',
          ...(auth.token ? { Authorization: `Bearer ${auth.token}` } : {}),
          ...((options.headers as Record<string, string>) ?? {}),
        },
      })
    }
    catch (error: unknown) {
      const status = (error as { response?: { status?: number } })?.response?.status
      if (status === 401 && retry && (await auth.tryRefresh())) {
        return api<T>(path, options, false)
      }
      if (status === 401) auth.logout()
      throw error
    }
  }

  return api
}
