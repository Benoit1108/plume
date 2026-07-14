import { describe, expect, it } from 'vitest'
import { errorToastTitle, isConflict } from '../utils/apiError'

const t = (key: string): string => key

describe('apiError', () => {
  it('reconnaît un 409 sous ses deux formes ofetch', () => {
    expect(isConflict({ statusCode: 409 })).toBe(true)
    expect(isConflict({ status: 409 })).toBe(true)
    expect(isConflict({ statusCode: 422 })).toBe(false)
    expect(isConflict(new Error('réseau'))).toBe(false)
    expect(isConflict(null)).toBe(false)
  })

  it('choisit le titre de toast selon le type d\'échec', () => {
    expect(errorToastTitle(t, { statusCode: 409 })).toBe('common.conflict')
    expect(errorToastTitle(t, { statusCode: 500 })).toBe('common.error')
  })
})
