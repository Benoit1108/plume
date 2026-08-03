<script setup lang="ts">
import type { AdminBilling } from '~/types/admin'

/** Back-office — abonnés par statut + revenu mensuel estimé (MRR). */
const { t, locale } = useI18n()
const adminApi = useAdmin()

const { data: billingData } = useQuery({ queryKey: queryKeys.adminBilling, queryFn: () => adminApi.billing() })
const billing = computed<AdminBilling | null>(() => billingData.value ?? null)
const euroFormat = computed(() => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }))
</script>

<template>
  <section v-if="billing" class="mt-8" :aria-label="t('admin.billing.title')">
    <h2 class="text-sm font-semibold">{{ t('admin.billing.title') }}</h2>
    <div class="mt-3 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.billing.mrr') }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums">{{ euroFormat.format(billing.estimatedMonthlyRevenue) }}</p>
        <p class="text-[10px] text-dimmed mt-1">{{ t('admin.billing.mrrHint') }}</p>
      </div>
      <div v-for="s in (['active', 'trialing', 'past_due', 'comped', 'canceled'] as const)" :key="s" class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t(`admin.billing.status.${s}`) }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums">{{ billing.byStatus[s] }}</p>
      </div>
    </div>
  </section>
</template>
