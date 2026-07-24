import type { QueryClient } from '@tanstack/vue-query'
import { describe, expect, it, vi } from 'vitest'
import { invalidateLeadRelated } from '../utils/invalidate'

function fakeClient(): { qc: QueryClient, keys: unknown[] } {
  const keys: unknown[] = []
  const qc = {
    invalidateQueries: vi.fn(({ queryKey }: { queryKey: unknown }) => {
      keys.push(queryKey)
      return Promise.resolve()
    }),
  } as unknown as QueryClient
  return { qc, keys }
}

describe('invalidateLeadRelated', () => {
  it('invalide toujours leads + today + dashboard', async () => {
    const { qc, keys } = fakeClient()
    await invalidateLeadRelated(qc)
    expect(keys).toEqual([['leads'], ['today'], ['dashboard']])
  })

  it('ajoute la fiche + le journal quand un id est fourni', async () => {
    const { qc, keys } = fakeClient()
    await invalidateLeadRelated(qc, 'lead-1')
    expect(keys).toContainEqual(['lead', 'lead-1'])
    expect(keys).toContainEqual(['lead', 'lead-1', 'timeline'])
  })
})
