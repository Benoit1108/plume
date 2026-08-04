import type { Ref } from 'vue'

/** Copie retardée d'une ref — évite une requête API à chaque frappe. */
export function useDebounced<T>(source: Ref<T>, delayMs = 300): Readonly<Ref<T>> {
  const debounced = ref(source.value) as Ref<T>
  let timer: ReturnType<typeof setTimeout> | undefined

  watch(source, (value) => {
    clearTimeout(timer)
    timer = setTimeout(() => {
      debounced.value = value
    }, delayMs)
  })

  return readonly(debounced) as Readonly<Ref<T>>
}
