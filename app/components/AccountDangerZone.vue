<script setup lang="ts">
/** Compte — zone de danger : suppression de compte (RGPD, soft-delete + purge à 30 j). */
const { t } = useI18n()
const auth = useAuthStore()
const accountApi = useAccount()
const toast = useToast()
const queryClient = useQueryClient()

const deleteModalOpen = ref(false)
const deletePassword = ref('')
const deleting = ref(false)

function openDeleteModal(): void {
  deletePassword.value = ''
  deleteModalOpen.value = true
}

async function deleteAccount(): Promise<void> {
  if (deleting.value || deletePassword.value === '') return
  deleting.value = true
  try {
    await accountApi.deleteAccount(deletePassword.value)
    deleteModalOpen.value = false
    toast.add({ title: t('account.danger.done'), color: 'success' })
    // Purge le cache serveur (TanStack) avant la déconnexion : aucune donnée du compte supprimé
    // ne doit subsister en mémoire après navigation SPA (poste partagé).
    queryClient.clear()
    auth.logout()
  }
  catch (error) {
    const detail = errorDetail(error)
    const key = detail === 'invalid_current_password'
      ? 'account.errors.invalidCurrent'
      : 'account.errors.generic'
    toast.add({ title: t(key), color: 'error' })
  }
  finally {
    deleting.value = false
  }
}
</script>

<template>
  <section class="border border-error/40 rounded-xl p-4 bg-error/5 flex flex-col gap-3">
    <div>
      <p class="text-sm font-semibold text-error">{{ t('account.danger.title') }}</p>
      <p class="text-xs text-muted mt-1">{{ t('account.danger.intro') }}</p>
    </div>
    <div class="flex justify-end">
      <UButton color="error" variant="soft" icon="i-lucide-trash-2" @click="openDeleteModal">
        {{ t('account.danger.button') }}
      </UButton>
    </div>

    <UModal v-model:open="deleteModalOpen" :title="t('account.danger.modalTitle')" :description="t('account.danger.modalIntro')">
      <template #body>
        <form class="flex flex-col gap-4" @submit.prevent="deleteAccount">
          <UFormField :label="t('account.danger.passwordLabel')">
            <UInput v-model="deletePassword" type="password" autocomplete="current-password" autofocus class="w-full" />
          </UFormField>
          <button type="submit" class="hidden" aria-hidden="true" tabindex="-1" />
        </form>
      </template>
      <template #footer>
        <div class="flex gap-2 justify-end w-full">
          <UButton color="neutral" variant="ghost" @click="() => { deleteModalOpen = false }">
            {{ t('actions.cancel') }}
          </UButton>
          <UButton color="error" :loading="deleting" :disabled="deletePassword === ''" @click="deleteAccount">
            {{ t('account.danger.confirm') }}
          </UButton>
        </div>
      </template>
    </UModal>
  </section>
</template>
