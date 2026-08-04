<script setup lang="ts">
import type { Subscription } from '~/types/domain/billing'

/** Bandeau « lecture seule » (V2.2) : visible quand l'abonnement ne donne plus le droit d'écrire. */
const { t } = useI18n()
const auth = useAuthStore()
const billing = useBilling()

const { data } = useQuery({
  queryKey: queryKeys.billingSubscription,
  queryFn: () => billing.subscription(),
  enabled: computed(() => auth.isAuthenticated),
})
const readOnly = computed<boolean>(() => {
  const sub = data.value as Subscription | undefined
  return sub ? !sub.entitled : false
})
</script>

<template>
  <UAlert
    v-if="readOnly"
    color="warning"
    variant="subtle"
    icon="i-lucide-lock"
    :title="t('billing.banner.title')"
    :description="t('billing.banner.hint')"
    class="rounded-none border-x-0 border-t-0"
  >
    <template #actions>
      <UButton size="xs" color="warning" to="/settings">{{ t('billing.banner.cta') }}</UButton>
    </template>
  </UAlert>
</template>
