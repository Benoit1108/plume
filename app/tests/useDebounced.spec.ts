import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { readonly, ref, watch } from 'vue'

vi.stubGlobal('ref', ref)
vi.stubGlobal('watch', watch)
vi.stubGlobal('readonly', readonly)

const { useDebounced } = await import('../composables/useDebounced')

describe('useDebounced', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('ne propage la valeur qu\'après le délai, en gardant la dernière frappe', async () => {
    const source = ref('a')
    const debounced = useDebounced(source, 300)

    source.value = 'ac'
    await vi.advanceTimersByTimeAsync(100)
    source.value = 'act'
    await vi.advanceTimersByTimeAsync(299)
    expect(debounced.value).toBe('a')

    await vi.advanceTimersByTimeAsync(1)
    expect(debounced.value).toBe('act')
  })
})
