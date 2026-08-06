<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()
const route = useRoute()
const toast = useToast()
const accountApi = useAccount()

const token = computed(() => (typeof route.query.token === 'string' ? route.query.token : ''))
const newPassword = ref('')
const confirmPassword = ref('')
const loading = ref(false)
const error = ref('')

const valid = computed(() => newPassword.value.length >= 8 && newPassword.value === confirmPassword.value)

async function onSubmit(): Promise<void> {
  error.value = ''
  if (newPassword.value.length < 8) { error.value = t('account.errors.tooShort'); return }
  if (newPassword.value !== confirmPassword.value) { error.value = t('account.errors.mismatch'); return }
  if (loading.value) return
  loading.value = true
  try {
    await accountApi.resetPassword(token.value, newPassword.value)
    toast.add({ title: t('auth.reset.done'), color: 'success' })
    await navigateTo('/login')
  }
  catch (e) {
    const detail = errorDetail(e)
    error.value = detail === 'invalid_new_password' ? t('account.errors.tooShort') : t('auth.reset.invalidToken')
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen grid place-items-center p-6">
    <div class="absolute top-4 right-4 flex gap-2">
      <LocaleSwitcher />
      <ThemeToggle />
    </div>
    <UCard class="w-full max-w-sm">
      <div class="flex flex-col gap-1.5">
        <PlumeMark :size="30" />
        <p class="text-sm text-muted">{{ t('auth.reset.intro') }}</p>
      </div>

      <UAlert v-if="token === ''" role="alert" color="error" variant="subtle" class="mt-6" :description="t('auth.reset.invalidToken')" />

      <form v-else method="post" class="mt-6 flex flex-col gap-4" @submit.prevent="onSubmit">
        <UFormField :label="t('account.password.new')" :hint="t('account.password.hint')">
          <UInput v-model="newPassword" type="password" autocomplete="new-password" required autofocus class="w-full" />
        </UFormField>
        <UFormField :label="t('account.password.confirm')">
          <UInput v-model="confirmPassword" type="password" autocomplete="new-password" required class="w-full" />
        </UFormField>

        <UAlert v-if="error" role="alert" color="error" variant="subtle" :description="error" />

        <UButton type="submit" :loading="loading" :disabled="!valid" block>
          {{ t('auth.reset.submit') }}
        </UButton>
        <NuxtLink to="/login" class="text-sm text-muted hover:text-default text-center">
          {{ t('auth.backToLogin') }}
        </NuxtLink>
      </form>
    </UCard>
  </div>
</template>
