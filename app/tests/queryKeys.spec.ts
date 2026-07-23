import { describe, expect, it } from 'vitest'
import { queryKeys } from '../utils/queryKeys'

describe('queryKeys', () => {
  it('expose des clés statiques stables', () => {
    expect(queryKeys.dashboard).toEqual(['dashboard'])
    expect(queryKeys.leads).toEqual(['leads'])
    expect(queryKeys.mailbox).toEqual(['mailbox'])
  })

  it('construit les clés paramétrées par id', () => {
    expect(queryKeys.lead('abc')).toEqual(['lead', 'abc'])
    expect(queryKeys.leadTimeline('abc')).toEqual(['lead', 'abc', 'timeline'])
    expect(queryKeys.organization('o1')).toEqual(['organization', 'o1'])
    expect(queryKeys.draftsForLead('l1')).toEqual(['drafts', 'l1'])
  })
})
