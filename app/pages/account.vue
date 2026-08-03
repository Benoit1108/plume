<script setup lang="ts">
// Page Compte : orchestrateur. Chaque bloc est un composant autonome (identité, mot de passe,
// sécurité, zone de danger) ; l'export RGPD reste inline (court). Découpage revue santé (lot F).
const { t } = useI18n()
const accountApi = useAccount()
const toast = useToast()

const exporting = ref(false)
async function exportData(): Promise<void> {
  if (exporting.value) return
  exporting.value = true
  try {
    const blob = await accountApi.exportData()
    downloadBlob(blob, `plume-export-${new Date().toISOString().slice(0, 10)}.zip`)
    toast.add({ title: t('account.export.done'), color: 'success' })
  }
  catch {
    toast.add({ title: t('account.export.error'), color: 'error' })
  }
  finally {
    exporting.value = false
  }
}
</script>

<template>
  <PageContainer width="atelier">
    <PageHeader :eyebrow="t('account.eyebrow')" :title="t('account.title')" />

    <div class="mt-6 flex flex-col gap-8 max-w-2xl">
      <IdentityForm />
      <PasswordChangeForm />

      <!-- Sécurité : 2FA + sessions actives -->
      <SecuritySection />

      <!-- Mes données : export RGPD (portabilité) -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-3">
        <div>
          <p class="text-sm font-semibold">{{ t('account.export.title') }}</p>
          <p class="text-xs text-muted mt-1">{{ t('account.export.intro') }}</p>
        </div>
        <div class="flex justify-end">
          <UButton variant="soft" icon="i-lucide-download" :loading="exporting" @click="exportData">
            {{ t('account.export.button') }}
          </UButton>
        </div>
      </section>

      <AccountDangerZone />
    </div>
  </PageContainer>
</template>
