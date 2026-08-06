import { describe, expect, it } from 'vitest'
import { errorDetail, errorStatus, errorToastTitle, isConflict } from '../utils/core/apiError'

/** `t` de test : renvoie la clé, pour vérifier QUEL message est choisi. */
const t = (key: string): string => key

/** Erreur $fetch : le statut arrive sous `response.status` (parfois recopié en `statusCode`). */
function httpError(status: number, detail?: string) {
  return { statusCode: status, response: { status }, data: detail === undefined ? {} : { detail } }
}

describe('errorStatus', () => {
  it('lit le code sous ses différentes formes', () => {
    expect(errorStatus({ statusCode: 402 })).toBe(402)
    expect(errorStatus({ status: 429 })).toBe(429)
    expect(errorStatus({ response: { status: 403 } })).toBe(403)
    expect(errorStatus('boom')).toBeUndefined()
    expect(errorStatus(null)).toBeUndefined()
  })
})

describe('isConflict', () => {
  it('ne reconnaît que le 409', () => {
    expect(isConflict(httpError(409))).toBe(true)
    expect(isConflict(httpError(422))).toBe(false)
  })
})

describe('errorDetail', () => {
  it('extrait le detail de problem+json quand il existe', () => {
    expect(errorDetail(httpError(422, 'name: trop long'))).toBe('name: trop long')
    expect(errorDetail(httpError(500))).toBeUndefined()
  })
})

describe('errorToastTitle', () => {
  it('dit POURQUOI quand le serveur le dit — pas « une erreur est survenue »', () => {
    expect(errorToastTitle(t, httpError(402, 'subscription_required'))).toBe('errors.subscriptionRequired')
    expect(errorToastTitle(t, httpError(429))).toBe('errors.tooManyRequests')
    expect(errorToastTitle(t, httpError(403, 'demo_restricted'))).toBe('errors.demoRestricted')
    expect(errorToastTitle(t, httpError(409))).toBe('common.conflict')
  })

  it('affiche le détail d\'une validation refusée', () => {
    expect(errorToastTitle(t, httpError(422, 'website: URL invalide'))).toBe('website: URL invalide')
    // ...mais jamais un 422 muet : on retombe sur le message générique.
    expect(errorToastTitle(t, httpError(422))).toBe('common.error')
  })

  it('reste générique pour un 403 ordinaire ou une panne serveur', () => {
    expect(errorToastTitle(t, httpError(403))).toBe('common.error')
    expect(errorToastTitle(t, httpError(500))).toBe('common.error')
    expect(errorToastTitle(t, new Error('réseau'))).toBe('common.error')
  })
})
