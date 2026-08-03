import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

// Le composable appelle onUnmounted (auto-importé Nuxt) : en env node, on le neutralise.
vi.stubGlobal('onUnmounted', vi.fn())

const { useCatchUpRefresh } = await import('../composables/useCatchUpRefresh')

describe('useCatchUpRefresh', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('planifie les rafraîchissements aux délais donnés', () => {
    const refetch = vi.fn()
    const { trigger } = useCatchUpRefresh(refetch, { schedule: [100, 300] })

    trigger()
    expect(refetch).toHaveBeenCalledTimes(0)
    vi.advanceTimersByTime(100)
    expect(refetch).toHaveBeenCalledTimes(1)
    vi.advanceTimersByTime(200)
    expect(refetch).toHaveBeenCalledTimes(2)
  })

  it('utilise une cadence par défaut si aucune n\'est fournie', () => {
    const refetch = vi.fn()
    const { trigger } = useCatchUpRefresh(refetch)

    trigger()
    vi.advanceTimersByTime(1000)
    expect(refetch).toHaveBeenCalledTimes(1)
  })

  it('un nouveau trigger annule la série précédente', () => {
    const refetch = vi.fn()
    const { trigger } = useCatchUpRefresh(refetch, { schedule: [100] })

    trigger()
    vi.advanceTimersByTime(50)
    trigger() // annule le timer encore en attente du 1er appel
    vi.advanceTimersByTime(50) // le 1er timer aurait tiré ici s'il n'était pas annulé
    expect(refetch).toHaveBeenCalledTimes(0)
    vi.advanceTimersByTime(50) // le 2e trigger tire à 100
    expect(refetch).toHaveBeenCalledTimes(1)
  })

  it('clear annule les rafraîchissements en attente', () => {
    const refetch = vi.fn()
    const { trigger, clear } = useCatchUpRefresh(refetch, { schedule: [100] })

    trigger()
    clear()
    vi.advanceTimersByTime(200)
    expect(refetch).toHaveBeenCalledTimes(0)
  })
})
