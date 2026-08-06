import type { MaybeRefOrGetter, WritableComputedRef } from 'vue'

/**
 * Onglet actif porté par l'URL (`?tab=…`) plutôt que par un état local.
 *
 * Sans ça, un onglet n'est pas atteignable : le bandeau d'abonnement, la notification « boîte
 * déconnectée » ou l'onboarding renvoient tous vers une page à onglets et doivent pouvoir viser
 * LE bon. Bonus : un rechargement (ou un lien partagé) rouvre le même onglet.
 *
 * Une valeur inconnue retombe sur le premier onglet — une URL bricolée n'affiche jamais du vide.
 */
export function useRouteTab(tabs: MaybeRefOrGetter<readonly string[]>, key = 'tab'): WritableComputedRef<string> {
  const route = useRoute()
  const router = useRouter()

  return computed({
    get: () => {
      const available = toValue(tabs)
      const value = route.query[key]
      return typeof value === 'string' && available.includes(value) ? value : (available[0] ?? '')
    },
    // `replace` : naviguer entre onglets ne remplit pas l'historique — le bouton Retour ramène à
    // l'écran précédent, pas à l'onglet d'avant.
    set: (value: string) => {
      void router.replace({ query: { ...route.query, [key]: value } })
    },
  })
}
