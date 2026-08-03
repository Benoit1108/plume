<script setup lang="ts">
import type { Subscription } from '~/types/billing'

/** Réglages — abonnement (V2.2) : état + s'abonner / gérer. Masqué si aucun abonnement (grandfathered). */
const { t } = useI18n()
const toast = useToast()
const billing = useBilling()

const { data: subData } = useQuery({ queryKey: queryKeys.billingSubscription, queryFn: () => billing.subscription() })
const subscription = computed<Subscription | null>(() => subData.value ?? null)
const trialDaysLeft = computed<number>(() => {
  const end = subscription.value?.trialEndsAt
  return end ? Math.max(0, Math.ceil((new Date(end).getTime() - Date.now()) / 86_400_000)) : 0
})

const billingBusy = ref(false)
async function subscribe(plan: 'monthly' | 'annual'): Promise<void> {
  if (billingBusy.value) return
  billingBusy.value = true
  try {
    const { url } = await billing.checkout(plan)
    window.location.href = url // redirection vers Stripe (ou retour app en factice)
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
    billingBusy.value = false
  }
}
async function manageBilling(): Promise<void> {
  if (billingBusy.value) return
  billingBusy.value = true
  try {
    const { url } = await billing.portal()
    window.location.href = url
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
    billingBusy.value = false
  }
}
</script>

<template>
  <section v-if="subscription && subscription.status !== 'none'" class="mt-6 border border-default rounded-xl p-4 bg-elevated/40 max-w-2xl">
    <div class="flex items-center gap-2 flex-wrap">
      <h2 class="text-sm font-semibold">{{ t('settings.billing.title') }}</h2>
      <UBadge :color="subscription.entitled ? 'success' : 'error'" variant="soft" size="sm">
        {{ t(`settings.billing.status.${subscription.status}`) }}
      </UBadge>
    </div>
    <p class="text-xs text-muted mt-1">
      <span v-if="subscription.status === 'trialing' && subscription.entitled">{{ t('settings.billing.trialLeft', { days: trialDaysLeft }, trialDaysLeft) }}</span>
      <span v-else-if="!subscription.entitled">{{ t('settings.billing.readOnly') }}</span>
      <span v-else>{{ t('settings.billing.activeHint') }}</span>
    </p>
    <div class="mt-3 flex items-center gap-2 flex-wrap">
      <UButton v-if="subscription.canManage" size="sm" variant="soft" :loading="billingBusy" @click="manageBilling">
        {{ t('settings.billing.manage') }}
      </UButton>
      <template v-else>
        <UButton size="sm" :loading="billingBusy" @click="subscribe('monthly')">{{ t('settings.billing.subscribeMonthly') }}</UButton>
        <UButton size="sm" variant="soft" :loading="billingBusy" @click="subscribe('annual')">{{ t('settings.billing.subscribeAnnual') }}</UButton>
      </template>
    </div>
  </section>
</template>
