<script setup lang="ts">
// Page Réglages : orchestrateur. Chaque domaine est un composant autonome (sa query + ses mutations) :
// abonnement, profil, boîte email, sources d'annonces. Découpage revue santé (lot F).
const { t } = useI18n()
</script>

<template>
  <PageContainer width="atelier">
    <PageHeader :eyebrow="t('settings.eyebrow')" :title="t('settings.title')" />

    <BillingSettingsSection />
    <ProfileSettingsForm />
    <MailboxSection />
    <AlertFeedsSection />
  </PageContainer>
</template>
