<script setup lang="ts">
/** Back-office — statut système opérationnel (files, backlog, boîtes, base) + garde-fou coût IA. */
const { t, locale } = useI18n()
const adminApi = useAdmin()

const { data: status } = useQuery({ queryKey: queryKeys.adminStatus, queryFn: () => adminApi.status() })
const systemHealthy = computed(() =>
  status.value !== undefined
  && status.value.failed === 0
  && status.value.mailboxesError === 0
  && status.value.backlogAgeSeconds < 600,
)
function formatTokens(n: number): string {
  return new Intl.NumberFormat(locale.value).format(n)
}
function backlogLabel(seconds: number): string {
  if (seconds < 60) return t('admin.status.backlogFresh')
  if (seconds < 3600) return t('admin.status.backlogMinutes', { count: Math.round(seconds / 60) })
  return t('admin.status.backlogHours', { count: Math.round(seconds / 3600) })
}
</script>

<template>
  <section v-if="status" class="mt-8" :aria-label="t('admin.status.title')">
    <div class="flex items-center gap-3">
      <h2 class="text-sm font-semibold">{{ t('admin.status.title') }}</h2>
      <UBadge :color="systemHealthy ? 'success' : 'warning'" variant="soft">
        {{ systemHealthy ? t('admin.status.healthy') : t('admin.status.attention') }}
      </UBadge>
    </div>
    <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3">
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.status.failed') }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums" :class="status.failed > 0 ? 'text-error' : ''">{{ status.failed }}</p>
      </div>
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.status.backlog') }}</p>
        <p class="text-lg font-semibold mt-1.5">{{ backlogLabel(status.backlogAgeSeconds) }}</p>
      </div>
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.status.mailboxesError') }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums" :class="status.mailboxesError > 0 ? 'text-error' : ''">{{ status.mailboxesError }}</p>
      </div>
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.status.db') }}</p>
        <p class="text-lg font-semibold mt-1.5">{{ status.db === 'ok' ? t('admin.status.dbOk') : status.db }}</p>
      </div>
    </div>

    <!-- Garde-fou coût IA : consommation du mois vs plafond + coupe-circuit -->
    <div class="mt-3 border border-default rounded-xl p-4 bg-elevated/40 flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-xs text-muted">{{ t('admin.status.aiUsage') }}</p>
        <p class="text-lg font-semibold mt-1.5 font-mono tabular-nums">
          {{ formatTokens(status.aiUsage.periodTokens) }}
          <span class="text-muted text-sm font-normal">
            / {{ status.aiUsage.monthlyTokenBudget > 0 ? formatTokens(status.aiUsage.monthlyTokenBudget) : t('admin.status.aiUnlimited') }}
            {{ t('admin.status.aiTokens') }}
          </span>
        </p>
        <p class="text-xs text-muted mt-0.5">{{ t('admin.status.aiCalls', { count: status.aiUsage.calls }, status.aiUsage.calls) }}</p>
      </div>
      <UBadge :color="status.aiUsage.enabled ? 'success' : 'error'" variant="soft">
        {{ status.aiUsage.enabled ? t('admin.status.aiEnabled') : t('admin.status.aiDisabled') }}
      </UBadge>
    </div>
  </section>
</template>
