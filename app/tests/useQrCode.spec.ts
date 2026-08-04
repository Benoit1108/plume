import { describe, expect, it, vi } from 'vitest'

// La lib qrcode est mockée : on teste le wrapper (options + valeur relayée), pas l'encodage.
const toDataURL = vi.fn((_text: string, _opts?: Record<string, unknown>) => Promise.resolve('data:image/png;base64,AAAA'))
vi.mock('qrcode', () => ({ default: { toDataURL } }))

const { useQrCode } = await import('../composables/core/useQrCode')

describe('useQrCode', () => {
  it('encode le texte en data-URL PNG avec des options locales', async () => {
    const url = await useQrCode().toDataUrl('otpauth://totp/Plume:marie?secret=ABC')

    expect(url).toBe('data:image/png;base64,AAAA')
    const call = toDataURL.mock.calls[0]
    expect(call?.[0]).toContain('otpauth://totp/')
    expect(call?.[1]).toMatchObject({ errorCorrectionLevel: 'M' })
  })
})
