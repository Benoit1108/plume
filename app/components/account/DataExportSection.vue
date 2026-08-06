<script setup lang="ts">
/** Mes données — export RGPD (portabilité) : archive ZIP JSON + CSV de tout le compte. */
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
</template>
