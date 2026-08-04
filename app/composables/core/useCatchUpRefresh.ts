/**
 * Rattrapage d'une opération ASYNCHRONE (projection worker, relève RSS…) : planifie une série de
 * rafraîchissements différés, avec nettoyage GARANTI au démontage. Factorise le motif (setTimeout +
 * cleanup) qui était réimplémenté différemment dans plusieurs écrans (revue santé F-P1).
 *
 * À appeler dans un `setup()` (utilise `onUnmounted`). `trigger()` (re)lance la série ; un nouvel
 * appel annule la précédente d'abord.
 */
export function useCatchUpRefresh(
  refetch: () => void,
  options: { schedule?: number[] } = {},
): { trigger: () => void, clear: () => void } {
  const schedule = options.schedule ?? [1000, 3000, 6000, 10000]
  const timers: ReturnType<typeof setTimeout>[] = []

  function clear(): void {
    timers.forEach(clearTimeout)
    timers.length = 0
  }

  function trigger(): void {
    clear()
    for (const delay of schedule) {
      timers.push(setTimeout(() => { refetch() }, delay))
    }
  }

  onUnmounted(clear)

  return { trigger, clear }
}
