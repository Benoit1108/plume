<script setup lang="ts">
import type { Dashboard } from '~/types/dashboard'
import type { LeadStatus } from '~/types/leads'

const { t, locale } = useI18n()
const { statusLabel } = useLeadLabels()
const { segmentLabel } = useDirectoryLabels()
const dashboardApi = useDashboard()

const { data, isPending: loading } = useQuery({
  queryKey: ['dashboard'],
  queryFn: () => dashboardApi.get(),
})
const board = computed<Dashboard | null>(() => data.value ?? null)

const percentFormat = computed(() => new Intl.NumberFormat(locale.value, { style: 'percent', maximumFractionDigits: 0 }))

// Calculs testés à part (décisions M1.5 n°1 et 2 : conversion = gagnées/décidées, taux par piste).
const { decided, conversion, responseRate, totalLeads, barHeightPercent, goalLinePercent, segmentRatio } = useDashboardMetrics(board)

/** Teintes par statut — mêmes familles que les badges du kanban. */
const STATUS_TINTS: Record<LeadStatus, string> = {
  TO_CONTACT: 'bg-neutral-400 dark:bg-neutral-500',
  CONTACTED: 'bg-primary/70',
  FOLLOWED_UP: 'bg-primary',
  IN_DISCUSSION: 'bg-info-500',
  SAMPLE_TEST: 'bg-warning-500',
  PAUSED: 'bg-neutral-300 dark:bg-neutral-600',
  WON: 'bg-success-500',
  LOST: 'bg-error-400',
}

function weekLabel(weekStart: string): string {
  return new Date(`${weekStart}T00:00:00`).toLocaleDateString(locale.value, { day: 'numeric', month: 'short' })
}

function segmentRate(contacted: number, replied: number): string {
  const ratio = segmentRatio(contacted, replied)
  return ratio === null ? '—' : percentFormat.value.format(ratio)
}

const hasActivity = computed(() =>
  Boolean(board.value && (totalLeads.value > 0 || board.value.contacted > 0)),
)
</script>

<template>
  <PageContainer width="atelier">
    <PageHeader :eyebrow="t('dashboard.eyebrow')" :title="t('dashboard.title')" />

    <div v-if="loading" role="status" class="mt-6 flex flex-col gap-4">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <USkeleton v-for="i in 4" :key="i" class="h-28 rounded-xl" />
      </div>
      <USkeleton class="h-24 rounded-xl" />
      <USkeleton class="h-44 rounded-xl" />
      <USkeleton class="h-48 rounded-xl" />
    </div>

    <template v-else-if="board">
      <div
        v-if="!hasActivity"
        class="mt-6 py-12 text-center text-muted border border-default rounded-xl"
      >
        {{ t('dashboard.empty') }}
      </div>

      <template v-else>
        <!-- KPIs -->
        <section class="mt-6 grid grid-cols-2 lg:grid-cols-4 gap-3 rise-stagger">
          <div class="border border-default rounded-xl p-4 bg-elevated/40">
            <p class="text-xs text-dimmed font-semibold uppercase tracking-wide">{{ t('dashboard.kpis.responseRate') }}</p>
            <p class="mt-1 font-serif text-3xl font-semibold tabular-nums">
              {{ responseRate === null ? '—' : percentFormat.format(responseRate) }}
            </p>
            <p class="text-xs text-muted mt-1">
              {{ board.contacted === 0
                ? t('dashboard.kpis.noneContacted')
                : t('dashboard.kpis.responseRateDetail', { replied: board.replied, contacted: board.contacted }) }}
            </p>
          </div>
          <div class="border border-default rounded-xl p-4 bg-elevated/40">
            <p class="text-xs text-dimmed font-semibold uppercase tracking-wide">{{ t('dashboard.kpis.conversion') }}</p>
            <p class="mt-1 font-serif text-3xl font-semibold tabular-nums">
              {{ conversion === null ? '—' : percentFormat.format(conversion) }}
            </p>
            <p class="text-xs text-muted mt-1">
              {{ decided === 0
                ? t('dashboard.kpis.noneDecided')
                : t('dashboard.kpis.conversionDetail', { won: board.won, decided }) }}
            </p>
          </div>
          <div class="border border-default rounded-xl p-4 bg-elevated/40">
            <p class="text-xs text-dimmed font-semibold uppercase tracking-wide">{{ t('dashboard.kpis.outreachThisMonth') }}</p>
            <p class="mt-1 font-serif text-3xl font-semibold tabular-nums">{{ board.outreachThisMonth }}</p>
            <p class="text-xs text-muted mt-1">{{ t('dashboard.kpis.outreachHint') }}</p>
          </div>
          <div class="border border-default rounded-xl p-4 bg-elevated/40">
            <p class="text-xs text-dimmed font-semibold uppercase tracking-wide">{{ t('dashboard.kpis.activeLeads') }}</p>
            <p class="mt-1 font-serif text-3xl font-semibold tabular-nums">{{ board.activeLeads }}</p>
            <p class="text-xs text-muted mt-1">{{ t('dashboard.pipeline.total', { count: totalLeads }, totalLeads) }}</p>
          </div>
        </section>

        <!-- Pipeline : barre segmentée + légende (les valeurs sont en texte, a11y) -->
        <section v-if="board.pipeline.length" class="mt-8 border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-sm font-semibold">{{ t('dashboard.pipeline.title') }}</p>
          <div
            class="mt-3 flex h-3 rounded-full overflow-hidden"
            role="img"
            :aria-label="board.pipeline.map(slice => `${statusLabel(slice.status)} : ${slice.count}`).join(', ')"
          >
            <div
              v-for="(slice, i) in board.pipeline"
              :key="slice.status"
              class="grow-x"
              :class="STATUS_TINTS[slice.status]"
              :style="{ width: `${(slice.count / Math.max(1, totalLeads)) * 100}%`, animationDelay: `${i * 0.05}s` }"
            />
          </div>
          <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs">
            <li v-for="slice in board.pipeline" :key="slice.status" class="flex items-center gap-1.5">
              <span class="size-2 rounded-full inline-block" :class="STATUS_TINTS[slice.status]" aria-hidden="true" />
              <span class="text-muted">{{ statusLabel(slice.status) }}</span>
              <span class="font-mono tabular-nums">{{ slice.count }}</span>
            </li>
          </ul>
        </section>

        <!-- Activité hebdomadaire : barres maison + ligne d'objectif -->
        <section class="mt-8 border border-default rounded-xl p-4 bg-elevated/40">
          <div class="flex items-baseline gap-3 flex-wrap">
            <p class="text-sm font-semibold">{{ t('dashboard.weekly.title') }}</p>
            <p class="text-xs text-dimmed">{{ t('dashboard.weekly.goalLine', { goal: board.weeklyTarget }) }}</p>
          </div>
          <div class="mt-4 relative">
            <!-- Zone des barres = h-24 en haut de chaque colonne : l'overlay s'y superpose. -->
            <div class="absolute inset-x-0 top-0 h-24 pointer-events-none" aria-hidden="true">
              <div class="absolute inset-x-0 border-t border-dashed border-primary/60" :style="{ bottom: `${goalLinePercent}%` }" />
            </div>
            <ol class="grid grid-cols-8 gap-2 items-end">
              <li v-for="(week, i) in board.weeklyActivity" :key="week.weekStart" class="flex flex-col items-center gap-1">
                <div class="h-24 w-full flex items-end">
                  <div
                    class="w-full rounded-t-sm min-h-0.5 grow-y"
                    :class="week.acts >= board.weeklyTarget ? 'bg-primary' : 'bg-primary/35'"
                    :style="{ height: `${barHeightPercent(week.acts)}%`, animationDelay: `${i * 0.05}s` }"
                    role="img"
                    :aria-label="`${t('dashboard.weekly.weekOf', { date: weekLabel(week.weekStart) })} : ${t('dashboard.weekly.acts', { count: week.acts }, week.acts)}`"
                  />
                </div>
                <span class="font-mono tabular-nums text-xs">{{ week.acts }}</span>
                <span class="text-[10px] text-dimmed whitespace-nowrap">{{ weekLabel(week.weekStart) }}</span>
              </li>
            </ol>
          </div>
        </section>

        <!-- Par segment -->
        <section v-if="board.segments.length" class="mt-8 border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-sm font-semibold">{{ t('dashboard.segments.title') }}</p>
          <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="text-left text-xs text-dimmed uppercase tracking-wide">
                  <th scope="col" class="py-1.5 pr-3 font-semibold">{{ t('dashboard.segments.segment') }}</th>
                  <th scope="col" class="py-1.5 px-3 font-semibold text-right">{{ t('dashboard.segments.contacted') }}</th>
                  <th scope="col" class="py-1.5 px-3 font-semibold text-right">{{ t('dashboard.segments.replied') }}</th>
                  <th scope="col" class="py-1.5 px-3 font-semibold text-right">{{ t('dashboard.segments.rate') }}</th>
                  <th scope="col" class="py-1.5 pl-3 font-semibold text-right">{{ t('dashboard.segments.won') }}</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[var(--ui-border)]">
                <tr v-for="row in board.segments" :key="row.segment" class="motion-safe:transition-colors hover:bg-elevated/50">
                  <td class="py-2 pr-3">
                    <NuxtLink
                      :to="`/leads?segment=${row.segment}`"
                      class="hover:text-primary underline-offset-2 hover:underline"
                      :aria-label="t('dashboard.segments.drillDown', { segment: segmentLabel(row.segment) })"
                    >
                      {{ segmentLabel(row.segment) }}
                    </NuxtLink>
                  </td>
                  <td class="py-2 px-3 text-right font-mono tabular-nums">{{ row.contacted }}</td>
                  <td class="py-2 px-3 text-right font-mono tabular-nums">{{ row.replied }}</td>
                  <td class="py-2 px-3 text-right font-mono tabular-nums">{{ segmentRate(row.contacted, row.replied) }}</td>
                  <td class="py-2 pl-3 text-right font-mono tabular-nums">{{ row.won }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </section>
      </template>
    </template>
  </PageContainer>
</template>
