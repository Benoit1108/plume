<script setup lang="ts">
// Page Réglages : orchestrateur en ONGLETS (la page empilait une dizaine de blocs à faire défiler).
// Chaque onglet est un composant autonome (sa query + ses mutations) ; l'onglet actif vit dans
// l'URL (?tab=) pour que les liens entrants — onboarding, notification, bandeau — visent juste.
const { t } = useI18n()
const billing = useBilling()

// L'onglet Abonnement n'existe que s'il y a un abonnement (comptes grandfathered : aucun).
// Même clé de cache que la section : pas de requête supplémentaire.
const { data: subData } = useQuery({ queryKey: queryKeys.billingSubscription, queryFn: () => billing.subscription() })
const hasSubscription = computed<boolean>(() => !!subData.value && subData.value.status !== 'none')

const items = computed(() => [
  { value: 'profile', slot: 'profile', label: t('settings.tabs.profile'), icon: 'i-lucide-user-round' },
  { value: 'prospecting', slot: 'prospecting', label: t('settings.tabs.prospecting'), icon: 'i-lucide-target' },
  { value: 'notifications', slot: 'notifications', label: t('settings.tabs.notifications'), icon: 'i-lucide-bell' },
  { value: 'mailbox', slot: 'mailbox', label: t('settings.tabs.mailbox'), icon: 'i-lucide-mail' },
  { value: 'sources', slot: 'sources', label: t('settings.tabs.sources'), icon: 'i-lucide-rss' },
  ...(hasSubscription.value ? [{ value: 'billing', slot: 'billing', label: t('settings.tabs.billing'), icon: 'i-lucide-credit-card' }] : []),
])

// Validé contre les onglets RÉELLEMENT présents : `?tab=billing` sur un compte sans abonnement
// retombe sur le premier onglet au lieu d'afficher une page vide.
const tab = useRouteTab(() => items.value.map(item => item.value))
</script>

<template>
  <PageContainer width="reading">
    <PageHeader :eyebrow="t('settings.eyebrow')" :title="t('settings.title')" />

    <!-- unmount-on-hide="false" : changer d'onglet ne doit jamais faire perdre une saisie en cours. -->
    <UTabs
      v-model="tab"
      :items="items"
      :unmount-on-hide="false"
      variant="link"
      class="mt-6 gap-6"
      :ui="{ list: 'overflow-x-auto overflow-y-hidden', trigger: 'shrink-0', indicator: 'h-0.5 rounded-full' }"
    >
      <template #profile><PresentationForm /></template>
      <template #prospecting><ProspectingSettingsForm /></template>
      <template #notifications><NotificationSettingsForm /></template>
      <template #mailbox><MailboxSection /></template>
      <template #sources><AlertFeedsSection /></template>
      <template #billing><BillingSettingsSection /></template>
    </UTabs>
  </PageContainer>
</template>
