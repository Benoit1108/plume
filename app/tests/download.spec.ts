import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const click = vi.fn()
const remove = vi.fn()
const link: Record<string, unknown> = { click, remove }
const appendChild = vi.fn()
const createObjectURL = vi.fn(() => 'blob:xyz')
const revokeObjectURL = vi.fn()

vi.stubGlobal('document', { createElement: vi.fn(() => link), body: { appendChild } })
// On PRÉSERVE la vraie classe URL (son constructeur est utilisé ailleurs) : on n'y greffe que
// les méthodes statiques absentes en env node.
vi.stubGlobal('URL', Object.assign(globalThis.URL, { createObjectURL, revokeObjectURL }))

const { downloadBlob } = await import('../utils/core/download')

describe('downloadBlob', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    click.mockClear()
    remove.mockClear()
    revokeObjectURL.mockClear()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('crée un lien nommé, le clique, puis révoque l\'URL objet en différé', () => {
    downloadBlob({} as unknown as Blob, 'export.csv')

    expect(createObjectURL).toHaveBeenCalled()
    expect(link.href).toBe('blob:xyz')
    expect(link.download).toBe('export.csv')
    expect(appendChild).toHaveBeenCalledWith(link)
    expect(click).toHaveBeenCalledTimes(1)
    expect(remove).toHaveBeenCalledTimes(1)

    // Révocation DIFFÉRÉE (pas synchrone).
    expect(revokeObjectURL).not.toHaveBeenCalled()
    vi.advanceTimersByTime(60_000)
    expect(revokeObjectURL).toHaveBeenCalledWith('blob:xyz')
  })
})
