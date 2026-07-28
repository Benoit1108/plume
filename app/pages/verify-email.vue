<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()
const route = useRoute()
const accountApi = useAccount()

const state = ref<'verifying' | 'ok' | 'error'>('verifying')

onMounted(async () => {
  const token = typeof route.query.token === 'string' ? route.query.token : ''
  if (token === '') { state.value = 'error'; return }
  try {
    await accountApi.verifyEmail(token)
    state.value = 'ok'
  }
  catch {
    state.value = 'error'
  }
})
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
      </div>

      <div class="mt-6 flex flex-col gap-4">
        <div v-if="state === 'verifying'" role="status" class="text-sm text-muted">
          {{ t('auth.verify.verifying') }}
        </div>
        <UAlert v-else-if="state === 'ok'" color="success" variant="subtle" :description="t('auth.verify.ok')" />
        <UAlert v-else color="error" variant="subtle" :description="t('auth.verify.error')" />

        <NuxtLink to="/login" class="text-sm text-muted hover:text-default text-center">
          {{ t('auth.backToLogin') }}
        </NuxtLink>
      </div>
    </UCard>
  </div>
</template>
