import { describe, expect, it } from 'vitest'
import { errorDetail, errorToastTitle, isConflict } from '../utils/apiError'

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

  it('extrait le détail problem+json quand il existe', () => {
    expect(errorDetail({ data: { detail: 'invalid_new_password' } })).toBe('invalid_new_password')
    expect(errorDetail({ data: {} })).toBeUndefined()
    expect(errorDetail({ data: { detail: 42 } })).toBeUndefined()
    expect(errorDetail(new Error('réseau'))).toBeUndefined()
    expect(errorDetail(null)).toBeUndefined()
  })
})
