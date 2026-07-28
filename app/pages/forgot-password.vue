<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()
const accountApi = useAccount()

const email = ref('')
const loading = ref(false)
const sent = ref(false)

async function onSubmit(): Promise<void> {
  if (loading.value) return
  loading.value = true
  try {
    await accountApi.requestPasswordReset(email.value.trim())
  }
  catch {
    // Anti-énumération : on n'expose jamais d'erreur spécifique — même écran de confirmation.
  }
  finally {
    loading.value = false
    sent.value = true
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
        <p class="text-sm text-muted">{{ t('auth.forgot.intro') }}</p>
      </div>

      <div v-if="sent" class="mt-6 flex flex-col gap-4">
        <UAlert color="success" variant="subtle" :description="t('auth.forgot.sent')" />
        <NuxtLink to="/login" class="text-sm text-muted hover:text-default text-center">
          {{ t('auth.backToLogin') }}
        </NuxtLink>
      </div>

      <form v-else method="post" class="mt-6 flex flex-col gap-4" @submit.prevent="onSubmit">
        <UFormField :label="t('auth.email')" name="email">
          <UInput v-model="email" type="email" autocomplete="username" required autofocus class="w-full" />
        </UFormField>
        <UButton type="submit" :loading="loading" block>
          {{ t('auth.forgot.submit') }}
        </UButton>
        <NuxtLink to="/login" class="text-sm text-muted hover:text-default text-center">
          {{ t('auth.backToLogin') }}
        </NuxtLink>
      </form>
    </UCard>
  </div>
</template>
