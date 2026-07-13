<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()
const auth = useAuthStore()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function onSubmit(): Promise<void> {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    await navigateTo('/')
  }
  catch {
    error.value = t('auth.error')
  }
  finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen grid place-items-center p-6">
    <UCard class="w-full max-w-sm">
      <div class="flex flex-col gap-1.5">
        <PlumeMark :size="30" />
        <p class="text-sm text-muted">{{ t('auth.tagline') }}</p>
      </div>

      <form class="mt-6 flex flex-col gap-4" @submit.prevent="onSubmit">
        <UFormField :label="t('auth.email')" name="email">
          <UInput v-model="email" type="email" autocomplete="username" required class="w-full" />
        </UFormField>
        <UFormField :label="t('auth.password')" name="password">
          <UInput v-model="password" type="password" autocomplete="current-password" required class="w-full" />
        </UFormField>

        <UAlert v-if="error" color="error" variant="subtle" :description="error" />

        <UButton type="submit" :loading="loading" block>
          {{ t('auth.signIn') }}
        </UButton>
      </form>
    </UCard>
  </div>
</template>
