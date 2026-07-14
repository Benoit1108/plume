import { describe, expect, it, vi } from 'vitest'
import { computed, ref } from 'vue'
import type { Dashboard } from '../types/dashboard'

vi.stubGlobal('computed', computed)
vi.stubGlobal('ref', ref)

const { useDashboardMetrics } = await import('../composables/useDashboardMetrics')

function aBoard(overrides: Partial<Dashboard> = {}): Dashboard {
  return {
    contacted: 0,
    replied: 0,
    won: 0,
    lost: 0,
    activeLeads: 0,
    outreachThisMonth: 0,
    weeklyTarget: 5,
    pipeline: [],
    weeklyActivity: [],
    segments: [],
    ...overrides,
  }
}

describe('useDashboardMetrics', () => {
  it('les taux sont null tant qu\'il n\'y a pas de matière (jamais de division par zéro)', () => {
    const metrics = useDashboardMetrics(ref<Dashboard | null>(aBoard()))

    expect(metrics.responseRate.value).toBeNull()
    expect(metrics.conversion.value).toBeNull()
    expect(metrics.decided.value).toBe(0)
    expect(metrics.segmentRatio(0, 0)).toBeNull()
  })

  it('conversion = gagnées / décidées (décision M1.5 n°1), taux de réponse par piste', () => {
    const metrics = useDashboardMetrics(ref<Dashboard | null>(aBoard({ contacted: 12, replied: 4, won: 1, lost: 3 })))

    expect(metrics.responseRate.value).toBeCloseTo(4 / 12)
    expect(metrics.decided.value).toBe(4)
    expect(metrics.conversion.value).toBeCloseTo(0.25)
    expect(metrics.segmentRatio(2, 1)).toBeCloseTo(0.5)
  })

  it('board null : tout retombe sur des valeurs sûres', () => {
    const metrics = useDashboardMetrics(ref<Dashboard | null>(null))

    expect(metrics.responseRate.value).toBeNull()
    expect(metrics.conversion.value).toBeNull()
    expect(metrics.totalLeads.value).toBe(0)
    expect(metrics.weeklyMax.value).toBe(1)
    expect(metrics.goalLinePercent.value).toBe(0)
  })

  it('l\'échelle des barres garde la ligne d\'objectif visible', () => {
    // Semaines calmes : le max est l'OBJECTIF (la ligne est à 100 %).
    const calm = useDashboardMetrics(ref<Dashboard | null>(aBoard({
      weeklyTarget: 5,
      weeklyActivity: [{ weekStart: '2026-07-06', acts: 2 }],
    })))
    expect(calm.weeklyMax.value).toBe(5)
    expect(calm.goalLinePercent.value).toBe(100)
    expect(calm.barHeightPercent(2)).toBe(40)
    expect(calm.barHeightPercent(0)).toBe(0)

    // Grosse semaine : le max est l'activité, la ligne descend proportionnellement.
    const busy = useDashboardMetrics(ref<Dashboard | null>(aBoard({
      weeklyTarget: 5,
      weeklyActivity: [{ weekStart: '2026-07-06', acts: 10 }],
    })))
    expect(busy.weeklyMax.value).toBe(10)
    expect(busy.goalLinePercent.value).toBe(50)
    expect(busy.barHeightPercent(10)).toBe(100)
  })

  it('totalLeads somme la répartition du pipeline', () => {
    const metrics = useDashboardMetrics(ref<Dashboard | null>(aBoard({
      pipeline: [
        { status: 'TO_CONTACT', count: 2 },
        { status: 'WON', count: 1 },
      ],
    })))

    expect(metrics.totalLeads.value).toBe(3)
  })
})
