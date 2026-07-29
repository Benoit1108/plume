<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()
const accountApi = useAccount()
const toast = useToast()

const email = ref('')
const password = ref('')
const confirmPassword = ref('')
const acceptTerms = ref(false)
const loading = ref(false)
const error = ref('')
const done = ref(false)

const valid = computed(() =>
  email.value.trim() !== ''
  && password.value.length >= 8
  && password.value === confirmPassword.value
  && acceptTerms.value,
)

async function onSubmit(): Promise<void> {
  error.value = ''
  if (password.value.length < 8) { error.value = t('account.errors.tooShort'); return }
  if (password.value !== confirmPassword.value) { error.value = t('account.errors.mismatch'); return }
  if (!acceptTerms.value) { error.value = t('auth.register.mustAccept'); return }
  if (loading.value) return
  loading.value = true
  try {
    await accountApi.register(email.value.trim(), password.value, acceptTerms.value)
    done.value = true
  }
  catch (e) {
    const status = (e as { response?: { status?: number } })?.response?.status
    error.value = status === 409 ? t('auth.register.emailTaken') : t('auth.register.error')
  }
  finally {
    loading.value = false
  }
}

async function resend(): Promise<void> {
  try {
    await accountApi.resendVerification(email.value.trim())
    toast.add({ title: t('auth.verify.resent'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
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
        <p class="text-sm text-muted">{{ t('auth.register.intro') }}</p>
      </div>

      <div v-if="done" class="mt-6 flex flex-col gap-4">
        <UAlert color="success" variant="subtle" :title="t('auth.register.checkEmailTitle')" :description="t('auth.register.checkEmail')" />
        <div class="flex flex-col gap-1.5 text-center">
          <button type="button" class="text-sm text-muted hover:text-default" @click="resend">
            {{ t('auth.verify.resend') }}
          </button>
          <NuxtLink to="/login" class="text-sm text-muted hover:text-default">
            {{ t('auth.backToLogin') }}
          </NuxtLink>
        </div>
      </div>

      <form v-else method="post" class="mt-6 flex flex-col gap-4" @submit.prevent="onSubmit">
        <UFormField :label="t('auth.email')" name="email">
          <UInput v-model="email" type="email" autocomplete="username" required autofocus class="w-full" />
        </UFormField>
        <UFormField :label="t('account.password.new')" :hint="t('account.password.hint')">
          <UInput v-model="password" type="password" autocomplete="new-password" required class="w-full" />
        </UFormField>
        <UFormField :label="t('account.password.confirm')">
          <UInput v-model="confirmPassword" type="password" autocomplete="new-password" required class="w-full" />
        </UFormField>

        <UCheckbox v-model="acceptTerms" required>
          <template #label>
            <i18n-t keypath="auth.register.acceptTerms" tag="span" class="text-sm">
              <template #terms><NuxtLink to="/legal/terms" class="underline">{{ t('legal.terms.title') }}</NuxtLink></template>
              <template #privacy><NuxtLink to="/legal/privacy" class="underline">{{ t('legal.privacy.title') }}</NuxtLink></template>
            </i18n-t>
          </template>
        </UCheckbox>

        <UAlert v-if="error" color="error" variant="subtle" :description="error" />

        <UButton type="submit" :loading="loading" :disabled="!valid" block>
          {{ t('auth.register.submit') }}
        </UButton>
        <NuxtLink to="/login" class="text-sm text-muted hover:text-default text-center">
          {{ t('auth.register.haveAccount') }}
        </NuxtLink>
      </form>
    </UCard>
  </div>
</template>
