import type { ComputedRef, MaybeRefOrGetter, Ref } from 'vue'

export interface ShowMore<T> {
  /** Éléments réellement affichés : les `initial` premiers, ou tous une fois déplié. */
  visible: ComputedRef<T[]>
  expanded: Ref<boolean>
  /** Nombre d'éléments masqués — 0 quand la liste tient sous le plafond. */
  hiddenCount: ComputedRef<number>
  toggle: () => void
}

/**
 * Plafonne une liste à N éléments avec un dépliage à la demande (lot « densité »).
 *
 * On plafonne au lieu de PLIER : replier une section entière permet de masquer du travail à faire,
 * alors qu'un « voir les N autres » dit toujours combien il en reste.
 */
export function useShowMore<T>(items: MaybeRefOrGetter<T[]>, initial: MaybeRefOrGetter<number> = 5): ShowMore<T> {
  const expanded = ref(false)
  const all = computed<T[]>(() => toValue(items))
  const cap = computed(() => Math.max(0, toValue(initial)))

  return {
    expanded,
    visible: computed(() => (expanded.value ? all.value : all.value.slice(0, cap.value))),
    hiddenCount: computed(() => Math.max(0, all.value.length - cap.value)),
    toggle: () => {
      expanded.value = !expanded.value
    },
  }
}
