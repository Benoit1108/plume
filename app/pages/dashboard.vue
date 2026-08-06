<script setup lang="ts">
import type { Dashboard, DashboardPeriod } from '~/types/domain/dashboard'

const { t, locale } = useI18n()
const { segmentLabel } = useDirectoryLabels()
const dashboardApi = useDashboard()

// Fenêtre des métriques du journal (taux, segments, délai). Change la clé de requête → refetch.
const period = ref<DashboardPeriod>('all')
const periodItems = computed(() => [
  { label: t('dashboard.period.all'), value: 'all' },
  { label: t('dashboard.period.30d'), value: '30d' },
  { label: t('dashboard.period.90d'), value: '90d' },
  { label: t('dashboard.period.12m'), value: '12m' },
])

const { data, isPending: loading, isError, refetch } = useQuery({
  queryKey: [...queryKeys.dashboard, period],
  queryFn: () => dashboardApi.get(period.value),
})
const board = computed<Dashboard | null>(() => data.value ?? null)

const percentFormat = computed(() => new Intl.NumberFormat(locale.value, { style: 'percent', maximumFractionDigits: 0 }))
const euroFormat = computed(() => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }))

// Calculs testés à part (décisions M1.5 n°1 et 2 : conversion = gagnées/décidées, taux par piste).
const { decided, conversion, responseRate, totalLeads, barHeightPercent, goalLinePercent, segmentRatio } = useDashboardMetrics(board)

// L'API omet la valeur null (aucune réponse encore) → on normalise en null (jamais undefined).
const firstResponseDelay = computed<number | null>(() => board.value?.firstResponseDelayDays ?? null)

const toast = useToast()
const exporting = ref(false)
async function exportCsv(): Promise<void> {
  if (exporting.value) return
  exporting.value = true
  try {
    const blob = await dashboardApi.exportCsv(period.value)
    downloadBlob(blob, `plume-tableau-de-bord-${new Date().toISOString().slice(0, 10)}.csv`)
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    exporting.value = false
  }
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
    <PageHeader :eyebrow="t('dashboard.eyebrow')" :title="t('dashboard.title')">
      <template #actions>
        <USelect
          v-model="period"
          :items="periodItems"
          value-key="value"
          label-key="label"
          icon="i-lucide-calendar-range"
          :aria-label="t('dashboard.period.label')"
          class="w-40"
        />
        <UButton color="neutral" variant="outline" icon="i-lucide-download" :loading="exporting" @click="exportCsv">
          {{ t('dashboard.export') }}
        </UButton>
      </template>
    </PageHeader>

    <div v-if="loading" role="status" class="mt-6 flex flex-col gap-4">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
        <USkeleton v-for="i in 4" :key="i" class="h-28 rounded-xl" />
      </div>
      <USkeleton class="h-24 rounded-xl" />
      <USkeleton class="h-44 rounded-xl" />
      <USkeleton class="h-48 rounded-xl" />
    </div>

    <QueryError v-else-if="isError" class="mt-6" @retry="() => { void refetch() }" />

    <template v-else-if="board">
      <div
        v-if="!hasActivity"
        class="mt-6 py-12 text-center text-muted border border-default rounded-xl"
      >
        {{ t('dashboard.empty') }}
      </div>

      <template v-else>
        <p v-if="period !== 'all'" class="mt-6 text-xs text-muted">
          {{ t('dashboard.period.scope', { period: periodItems.find(p => p.value === period)?.label }) }}
        </p>
        <!-- KPIs — les deux premiers portent la thèse du produit (obtient-on des réponses, et
             les transforme-t-on ?) et dominent ; les quatre autres complètent (revue design). -->
        <section class="mt-4 grid grid-cols-2 lg:grid-cols-4 gap-3 rise-stagger">
          <DashboardKpiCard
            emphasis
            :label="t('dashboard.kpis.responseRate')"
            :value="responseRate === null ? '—' : percentFormat.format(responseRate)"
            :hint="board.contacted === 0 ? t('dashboard.kpis.noneContacted') : t('dashboard.kpis.responseRateDetail', { replied: board.replied, contacted: board.contacted })"
          />
          <DashboardKpiCard
            emphasis
            :label="t('dashboard.kpis.conversion')"
            :value="conversion === null ? '—' : percentFormat.format(conversion)"
            :hint="decided === 0 ? t('dashboard.kpis.noneDecided') : t('dashboard.kpis.conversionDetail', { won: board.won, decided })"
          />
          <DashboardKpiCard :label="t('dashboard.kpis.outreachThisMonth')" :value="String(board.outreachThisMonth)" :hint="t('dashboard.kpis.outreachHint')" />
          <DashboardKpiCard :label="t('dashboard.kpis.activeLeads')" :value="String(board.activeLeads)" :hint="t('dashboard.pipeline.total', { count: totalLeads }, totalLeads)" />
          <DashboardKpiCard
            :label="t('dashboard.kpis.firstResponse')"
            :value="formatResponseDelay(firstResponseDelay, (unit, value) => t(`dashboard.kpis.firstResponse${unit === 'days' ? 'Days' : unit === 'hours' ? 'Hours' : 'Minutes'}`, { value }, value))"
            :hint="t('dashboard.kpis.firstResponseHint')"
          />
          <DashboardKpiCard
            :label="t('dashboard.kpis.pipelineValue')"
            :value="euroFormat.format(board.pipelineValue)"
            :hint="t('dashboard.kpis.wonValue', { value: euroFormat.format(board.wonValue) })"
          />
        </section>

        <DashboardPipelineBar :pipeline="board.pipeline" :total="totalLeads" />

        <DashboardWeeklyActivityChart
          :weeks="board.weeklyActivity"
          :target="board.weeklyTarget"
          :bar-height-percent="barHeightPercent"
          :goal-line-percent="goalLinePercent"
        />

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
