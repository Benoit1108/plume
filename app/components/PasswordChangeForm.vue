<script setup lang="ts">
/** Compte — connexion (email en lecture seule) + changement de mot de passe. */
const { t } = useI18n()
const auth = useAuthStore()
const accountApi = useAccount()
const toast = useToast()

const currentPassword = ref('')
const newPassword = ref('')
const confirmPassword = ref('')
const changingPassword = ref(false)

const passwordValid = computed(() =>
  currentPassword.value !== ''
  && newPassword.value.length >= 8
  && newPassword.value === confirmPassword.value,
)

async function changePassword(): Promise<void> {
  if (newPassword.value.length < 8) {
    toast.add({ title: t('account.errors.tooShort'), color: 'error' })
    return
  }
  if (newPassword.value !== confirmPassword.value) {
    toast.add({ title: t('account.errors.mismatch'), color: 'error' })
    return
  }
  changingPassword.value = true
  try {
    await accountApi.changePassword(currentPassword.value, newPassword.value)
    currentPassword.value = ''
    newPassword.value = ''
    confirmPassword.value = ''
    toast.add({ title: t('account.toasts.passwordChanged'), color: 'success' })
  }
  catch (error) {
    const detail = errorDetail(error)
    const key = detail === 'invalid_current_password'
      ? 'account.errors.invalidCurrent'
      : detail === 'invalid_new_password'
        ? 'account.errors.tooShort'
        : 'account.errors.generic'
    toast.add({ title: t(key), color: 'error' })
  }
  finally {
    changingPassword.value = false
  }
}
</script>

<template>
  <section class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-4">
    <p class="text-sm font-semibold">{{ t('account.login.title') }}</p>
    <UFormField :label="t('account.login.email')" :hint="t('account.login.emailHint')">
      <UInput :model-value="auth.email ?? ''" disabled readonly class="w-full" />
    </UFormField>

    <form class="flex flex-col gap-4 border-t border-default pt-4" @submit.prevent="changePassword">
      <p class="text-sm font-semibold">{{ t('account.password.title') }}</p>
      <UFormField :label="t('account.password.current')">
        <UInput v-model="currentPassword" type="password" autocomplete="current-password" class="w-full" />
      </UFormField>
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <UFormField :label="t('account.password.new')" :hint="t('account.password.hint')">
          <UInput v-model="newPassword" type="password" autocomplete="new-password" class="w-full" />
        </UFormField>
        <UFormField :label="t('account.password.confirm')">
          <UInput v-model="confirmPassword" type="password" autocomplete="new-password" class="w-full" />
        </UFormField>
      </div>
      <div class="flex justify-end">
        <UButton type="submit" :loading="changingPassword" :disabled="!passwordValid">
          {{ t('account.password.submit') }}
        </UButton>
      </div>
    </form>
  </section>
</template>
