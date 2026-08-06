import { describe, expect, it } from 'vitest'
import { formatSessionDevice } from '../utils/account/sessionDevice'

describe('formatSessionDevice', () => {
  it('assemble navigateur et plateforme', () => {
    expect(formatSessionDevice({ browser: 'Firefox', platform: 'Linux' }, 'Appareil inconnu')).toBe('Firefox · Linux')
  })

  it('n\'affiche que ce qui est connu', () => {
    expect(formatSessionDevice({ browser: 'Safari', platform: null }, 'Appareil inconnu')).toBe('Safari')
    expect(formatSessionDevice({ browser: null, platform: 'iPhone' }, 'Appareil inconnu')).toBe('iPhone')
  })

  it('retombe sur le libellé traduit quand rien n\'est connu (session d\'avant la migration)', () => {
    expect(formatSessionDevice({ browser: null, platform: null }, 'Appareil inconnu')).toBe('Appareil inconnu')
    expect(formatSessionDevice({ browser: '  ', platform: '' }, 'Unknown device')).toBe('Unknown device')
    expect(formatSessionDevice({}, 'Appareil inconnu')).toBe('Appareil inconnu')
  })
})
