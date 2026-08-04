<script setup lang="ts">
import type { AdminTrends } from '~/types/domain/admin'

/** Back-office — croissance & acquisition : comptes actifs par semaine + entonnoir d'inscription. */
const { t, locale } = useI18n()
const adminApi = useAdmin()

const { data: trendsData } = useQuery({ queryKey: queryKeys.adminTrends, queryFn: () => adminApi.trends() })
const trends = computed<AdminTrends | null>(() => trendsData.value ?? null)
const activeMax = computed(() => Math.max(1, ...(trends.value?.weeklyActive ?? []).map(w => w.count)))
const funnelStages = computed(() => {
  const f = trends.value?.funnel
  return f
    ? ([
        { key: 'signedUp', count: f.signedUp },
        { key: 'verified', count: f.verified },
        { key: 'activated', count: f.activated },
        { key: 'active30d', count: f.active30d },
      ] as const)
    : []
})
function funnelPercent(count: number): number {
  const total = trends.value?.funnel.signedUp ?? 0
  return total > 0 ? Math.round((count / total) * 100) : 0
}
function weekLabel(iso: string): string {
  return new Date(`${iso}T00:00:00`).toLocaleDateString(locale.value, { day: 'numeric', month: 'short' })
}
</script>

<template>
  <section v-if="trends" class="mt-8" :aria-label="t('admin.trends.title')">
    <h2 class="text-sm font-semibold">{{ t('admin.trends.title') }}</h2>
    <div class="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-3">
      <!-- Courbe des comptes actifs par semaine -->
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.trends.activeWeekly') }}</p>
        <ol class="mt-4 grid grid-cols-12 gap-1 items-end">
          <li v-for="week in trends.weeklyActive" :key="week.week" class="flex flex-col items-center gap-1">
            <div class="h-20 w-full flex items-end">
              <div
                class="w-full rounded-t-sm bg-primary/70 min-h-0.5"
                :style="{ height: `${(week.count / activeMax) * 100}%` }"
                role="img"
                :aria-label="`${weekLabel(week.week)} : ${week.count}`"
              />
            </div>
            <span class="font-mono tabular-nums text-[10px]">{{ week.count }}</span>
          </li>
        </ol>
        <p class="text-[10px] text-dimmed mt-1">{{ t('admin.trends.activeWeeklyHint') }}</p>
      </div>
      <!-- Entonnoir d'acquisition -->
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.trends.funnel') }}</p>
        <ul class="mt-3 flex flex-col gap-2">
          <li v-for="stage in funnelStages" :key="stage.key">
            <div class="flex items-baseline justify-between text-sm">
              <span>{{ t(`admin.trends.stages.${stage.key}`) }}</span>
              <span class="font-mono tabular-nums text-xs text-muted">{{ stage.count }} · {{ funnelPercent(stage.count) }} %</span>
            </div>
            <div class="mt-1 h-2 rounded-full bg-elevated overflow-hidden">
              <div class="h-full bg-primary/70 rounded-full" :style="{ width: `${funnelPercent(stage.count)}%` }" />
            </div>
          </li>
        </ul>
      </div>
    </div>
  </section>
</template>
