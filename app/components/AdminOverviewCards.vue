<script setup lang="ts">
/** Back-office — vue d'ensemble : comptages uniquement (jamais de contenu métier). */
const { t } = useI18n()
const adminApi = useAdmin()

const { data: overview, isError: overviewError } = useQuery({
  queryKey: queryKeys.adminOverview,
  queryFn: () => adminApi.overview(),
})
const failedDepth = computed(() => overview.value?.queues.failed ?? 0)
</script>

<template>
  <UAlert v-if="overviewError" color="error" variant="subtle" class="mt-6" :description="t('common.error')" />

  <section v-if="overview" class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-3" :aria-label="t('admin.overview.title')">
    <div class="border border-default rounded-xl p-4 bg-elevated/40">
      <p class="text-xs text-muted">{{ t('admin.overview.accounts') }}</p>
      <p class="text-2xl font-semibold font-mono tabular-nums">{{ overview.accounts.total }}</p>
      <p class="text-xs text-muted mt-1">
        {{ t('admin.overview.accountsDetail', { unverified: overview.accounts.unverified, deleting: overview.accounts.pendingDeletion }) }}
      </p>
    </div>
    <div class="border border-default rounded-xl p-4 bg-elevated/40">
      <p class="text-xs text-muted">{{ t('admin.overview.activity') }}</p>
      <p class="text-2xl font-semibold font-mono tabular-nums">{{ overview.business.leads }}</p>
      <p class="text-xs text-muted mt-1">
        {{ t('admin.overview.activityDetail', { orgs: overview.business.organizations, sent: overview.business.messagesSent }) }}
      </p>
    </div>
    <div class="border border-default rounded-xl p-4 bg-elevated/40">
      <p class="text-xs text-muted">{{ t('admin.overview.mailboxes') }}</p>
      <p class="text-2xl font-semibold font-mono tabular-nums">{{ overview.business.mailboxesConnected }}</p>
      <p class="text-xs mt-1" :class="overview.business.mailboxesError > 0 ? 'text-error' : 'text-muted'">
        {{ t('admin.overview.mailboxesError', { count: overview.business.mailboxesError }) }}
      </p>
    </div>
    <div class="border border-default rounded-xl p-4 bg-elevated/40">
      <p class="text-xs text-muted">{{ t('admin.overview.queues') }}</p>
      <p class="text-2xl font-semibold font-mono tabular-nums" :class="failedDepth > 0 ? 'text-error' : ''">
        {{ failedDepth }}
      </p>
      <p class="text-xs text-muted mt-1">{{ t('admin.overview.queuesDetail') }}</p>
    </div>
  </section>
</template>
