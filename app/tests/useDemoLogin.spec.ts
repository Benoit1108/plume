import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

// Neutralise / mocke les auto-imports Nuxt utilisés par le composable (env node).
const enterDemoMock = vi.fn()
const toastAdd = vi.fn()
const navigateTo = vi.fn()

vi.stubGlobal('ref', ref)
vi.stubGlobal('useI18n', () => ({ t: (k: string) => k }))
vi.stubGlobal('useAuthStore', () => ({ enterDemo: enterDemoMock }))
vi.stubGlobal('useToast', () => ({ add: toastAdd }))
vi.stubGlobal('navigateTo', navigateTo)

const { useDemoLogin } = await import('../composables/landing/useDemoLogin')

describe('useDemoLogin', () => {
  beforeEach(() => {
    enterDemoMock.mockReset()
    toastAdd.mockReset()
    navigateTo.mockReset()
  })

  it('monte le compte démo puis redirige vers /today', async () => {
    enterDemoMock.mockResolvedValue(undefined)
    const { entering, enterDemo } = useDemoLogin()

    expect(entering.value).toBe(false)
    await enterDemo()

    expect(enterDemoMock).toHaveBeenCalledOnce()
    expect(navigateTo).toHaveBeenCalledWith('/today')
    expect(entering.value).toBe(false)
  })

  it('affiche une erreur et ne redirige pas si le montage échoue', async () => {
    enterDemoMock.mockRejectedValue(new Error('boom'))
    const { enterDemo } = useDemoLogin()

    await enterDemo()

    expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ color: 'error' }))
    expect(navigateTo).not.toHaveBeenCalled()
  })

  it('ignore un second appel pendant le montage', async () => {
    let release: () => void = () => {}
    enterDemoMock.mockReturnValue(new Promise<void>((r) => { release = r }))
    const { entering, enterDemo } = useDemoLogin()

    const first = enterDemo()
    expect(entering.value).toBe(true)
    const second = enterDemo() // doit être ignoré (montage en cours)
    release()
    await Promise.all([first, second])

    expect(enterDemoMock).toHaveBeenCalledOnce()
    expect(entering.value).toBe(false)
  })
})
