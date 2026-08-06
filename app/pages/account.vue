<script setup lang="ts">
// Page Compte : orchestrateur en ONGLETS (identité / sécurité / mes données). Chaque bloc est un
// composant autonome ; l'onglet actif vit dans l'URL (?tab=), comme sur Réglages.
const { t } = useI18n()

const items = computed(() => [
  { value: 'profile', slot: 'profile', label: t('account.tabs.profile'), icon: 'i-lucide-circle-user' },
  { value: 'security', slot: 'security', label: t('account.tabs.security'), icon: 'i-lucide-shield-check' },
  { value: 'data', slot: 'data', label: t('account.tabs.data'), icon: 'i-lucide-database' },
])

const tab = useRouteTab(['profile', 'security', 'data'])
</script>

<template>
  <PageContainer width="reading">
    <PageHeader :eyebrow="t('account.eyebrow')" :title="t('account.title')" />

    <!-- unmount-on-hide="false" : changer d'onglet ne doit jamais faire perdre une saisie en cours. -->
    <UTabs
      v-model="tab"
      :items="items"
      :unmount-on-hide="false"
      variant="link"
      class="mt-6 gap-6"
      :ui="{ list: 'overflow-x-auto overflow-y-hidden', trigger: 'shrink-0', indicator: 'h-0.5 rounded-full' }"
    >
      <template #profile>
        <div class="flex flex-col gap-6">
          <IdentityForm />
        </div>
      </template>

      <template #security>
        <div class="flex flex-col gap-6">
          <PasswordChangeForm />
          <SecuritySection />
        </div>
      </template>

      <template #data>
        <div class="flex flex-col gap-6">
          <DataExportSection />
          <AccountDangerZone />
        </div>
      </template>
    </UTabs>
  </PageContainer>
</template>
