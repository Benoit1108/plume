<script setup lang="ts">
import type { AdminAlerts } from '~/types/domain/admin'

/** Back-office — santé & alertes : la liste « à regarder » (inactifs, boîtes en erreur, vérifs en souffrance). */
const { t } = useI18n()
const adminApi = useAdmin()

const { data: alertsData } = useQuery({ queryKey: queryKeys.adminAlerts, queryFn: () => adminApi.alerts() })
const alerts = computed<AdminAlerts | null>(() => alertsData.value ?? null)
const alertsCount = computed(() => {
  const a = alerts.value
  return a ? a.inactiveAccounts.length + a.mailboxesInError.length + a.stuckVerification.length : 0
})
</script>

<template>
  <section v-if="alerts" class="mt-8" :aria-label="t('admin.alerts.title')">
    <div class="flex items-center gap-2">
      <h2 class="text-sm font-semibold">{{ t('admin.alerts.title') }}</h2>
      <UBadge :color="alertsCount > 0 ? 'warning' : 'success'" variant="soft" size="sm">
        {{ alertsCount > 0 ? t('admin.alerts.count', { count: alertsCount }, alertsCount) : t('admin.alerts.allClear') }}
      </UBadge>
    </div>
    <div v-if="alertsCount > 0" class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs font-semibold">{{ t('admin.alerts.inactive') }} ({{ alerts.inactiveAccounts.length }})</p>
        <p class="text-xs text-muted mt-0.5">{{ t('admin.alerts.inactiveHint') }}</p>
        <ul class="mt-2 flex flex-col gap-1 text-sm">
          <li v-for="a in alerts.inactiveAccounts" :key="a.email" class="truncate">{{ a.email }}</li>
          <li v-if="alerts.inactiveAccounts.length === 0" class="text-muted text-xs">{{ t('admin.alerts.none') }}</li>
        </ul>
      </div>
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs font-semibold">{{ t('admin.alerts.mailboxError') }} ({{ alerts.mailboxesInError.length }})</p>
        <p class="text-xs text-muted mt-0.5">{{ t('admin.alerts.mailboxErrorHint') }}</p>
        <ul class="mt-2 flex flex-col gap-1 text-sm">
          <li v-for="a in alerts.mailboxesInError" :key="a.email" class="truncate">{{ a.email }}</li>
          <li v-if="alerts.mailboxesInError.length === 0" class="text-muted text-xs">{{ t('admin.alerts.none') }}</li>
        </ul>
      </div>
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs font-semibold">{{ t('admin.alerts.stuckVerification') }} ({{ alerts.stuckVerification.length }})</p>
        <p class="text-xs text-muted mt-0.5">{{ t('admin.alerts.stuckVerificationHint') }}</p>
        <ul class="mt-2 flex flex-col gap-1 text-sm">
          <li v-for="a in alerts.stuckVerification" :key="a.email" class="truncate">{{ a.email }}</li>
          <li v-if="alerts.stuckVerification.length === 0" class="text-muted text-xs">{{ t('admin.alerts.none') }}</li>
        </ul>
      </div>
    </div>
  </section>
</template>
