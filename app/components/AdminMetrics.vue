<script setup lang="ts">
/** Back-office — métriques produit : totaux, actifs 30 j, inscriptions/semaine, pistes par statut. */
const { t, locale } = useI18n()
const adminApi = useAdmin()

const { data: metrics } = useQuery({ queryKey: queryKeys.adminMetrics, queryFn: () => adminApi.metrics() })
/** Bornes des barres d'inscriptions (hauteur relative au pic). */
const signupPeak = computed(() => Math.max(1, ...(metrics.value?.signups ?? []).map(s => s.count)))
function shortWeek(week: string): string {
  return new Date(week).toLocaleDateString(locale.value, { day: 'numeric', month: 'short' })
}
</script>

<template>
  <section v-if="metrics" class="mt-8" :aria-label="t('admin.metrics.title')">
    <h2 class="text-sm font-semibold">{{ t('admin.metrics.title') }}</h2>
    <div class="mt-3 grid grid-cols-2 md:grid-cols-3 gap-3">
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.metrics.total') }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums">{{ metrics.accounts.total }}</p>
        <p class="text-xs text-muted mt-1">{{ t('admin.metrics.verified', { count: metrics.accounts.verified }) }}</p>
      </div>
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.metrics.active') }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums">{{ metrics.accounts.active30d }}</p>
        <p class="text-xs text-muted mt-1">{{ t('admin.metrics.activeHint') }}</p>
      </div>
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.metrics.messagesSent') }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums">{{ metrics.totals.messagesSent }}</p>
        <p class="text-xs text-muted mt-1">{{ t('admin.metrics.totalsHint', { orgs: metrics.totals.organizations, leads: metrics.totals.leads }) }}</p>
      </div>
    </div>

    <!-- Inscriptions (8 dernières semaines) -->
    <div class="mt-3 border border-default rounded-xl p-4 bg-elevated/40">
      <p class="text-xs text-muted">{{ t('admin.metrics.signups') }}</p>
      <p v-if="metrics.signups.length === 0" class="text-sm text-muted mt-2">{{ t('admin.metrics.signupsEmpty') }}</p>
      <ol v-else class="mt-3 flex items-end gap-2 h-20">
        <li v-for="week in metrics.signups" :key="week.week" class="flex flex-col items-center gap-1 flex-1">
          <span class="font-mono tabular-nums text-xs">{{ week.count }}</span>
          <div class="w-full bg-primary/60 rounded-t-sm min-h-0.5" :style="{ height: `${Math.round((week.count / signupPeak) * 100)}%` }" role="img" :aria-label="`${shortWeek(week.week)} : ${week.count}`" />
          <span class="text-[10px] text-dimmed whitespace-nowrap">{{ shortWeek(week.week) }}</span>
        </li>
      </ol>
    </div>

    <!-- Répartition des pistes par statut -->
    <div class="mt-3 border border-default rounded-xl p-4 bg-elevated/40">
      <p class="text-xs text-muted">{{ t('admin.metrics.leadsByStatus') }}</p>
      <div class="mt-2 flex flex-wrap gap-2">
        <UBadge v-for="(count, leadStatus) in metrics.leadsByStatus" :key="leadStatus" color="neutral" variant="soft">
          {{ t(`pipeline.statuses.${leadStatus}`) }} · {{ count }}
        </UBadge>
      </div>
    </div>
  </section>
</template>
