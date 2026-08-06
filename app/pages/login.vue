<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()
const auth = useAuthStore()
const accountApi = useAccount()
const toast = useToast()

useSeoMeta({ title: () => `${t('seo.loginTitle')} · Plume` })

const email = ref('')
const password = ref('')
const otp = ref('')
const otpRequired = ref(false)
const needsVerification = ref(false)
const error = ref('')
const loading = ref(false)

async function onSubmit(): Promise<void> {
  error.value = ''
  needsVerification.value = false
  loading.value = true
  try {
    await auth.login(email.value, password.value, otpRequired.value ? otp.value : undefined)
    await navigateTo('/')
  }
  catch (e) {
    // Codes stables renvoyés par l'API (AccountStatusChecker + listener 2FA).
    const message = (e as { data?: { message?: string } })?.data?.message ?? ''
    if (message === '2fa_required') {
      otpRequired.value = true
    }
    else if (message === '2fa_invalid') {
      otpRequired.value = true
      error.value = t('auth.otpInvalid')
    }
    else if (message === 'email_not_verified') {
      needsVerification.value = true // mot de passe BON, email à confirmer → proposer le renvoi
    }
    else if (message === 'account_deleted') {
      error.value = t('auth.accountDeleted')
    }
    else {
      error.value = t('auth.error')
    }
  }
  finally {
    loading.value = false
  }
}

async function resendVerification(): Promise<void> {
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
  <div class="relative min-h-screen grid place-items-center overflow-hidden p-6">
    <!-- Décor de marque volontairement INERTE (halo + plume en filigrane) : cette page est vue
         quotidiennement, un mouvement continu y deviendrait une nuisance. Seule une entrée en fondu
         au chargement, qui ne se répète pas pendant l'usage. -->
    <div class="ink-bloom pointer-events-none absolute inset-x-0 top-0 h-80" aria-hidden="true" />
    <LandingFeather class="pointer-events-none absolute top-10 -right-6 w-[130px] md:w-[170px] rotate-8 text-primary opacity-[0.13]" />

    <div class="absolute top-4 right-4 z-10 flex gap-2">
      <LocaleSwitcher />
      <ThemeToggle />
    </div>
    <UCard class="rise relative w-full max-w-sm">
      <div class="flex flex-col gap-1.5">
        <!-- Seule sortie de cette page : sans elle, un visiteur venu de la vitrine est dans un
             cul-de-sac (aucun lien de retour, et pas de flèche visible sur mobile). -->
        <NuxtLink to="/" class="inline-flex w-fit rounded-md focus-visible:outline-2 focus-visible:outline-primary" :aria-label="t('nav.home')">
          <PlumeMark :size="30" />
        </NuxtLink>
        <p class="text-sm text-muted">{{ t('auth.tagline') }}</p>
      </div>

      <!-- method="post" : si un submit part AVANT l'hydratation (handler pas encore
           attaché), le navigateur POSTe au lieu de mettre le mot de passe dans l'URL. -->
      <form method="post" class="mt-6 flex flex-col gap-4" @submit.prevent="onSubmit">
        <UFormField :label="t('auth.email')" name="email">
          <UInput v-model="email" type="email" autocomplete="username" required class="w-full" />
        </UFormField>
        <UFormField :label="t('auth.password')" name="password">
          <UInput v-model="password" type="password" autocomplete="current-password" required class="w-full" />
        </UFormField>

        <UFormField v-if="otpRequired" :label="t('auth.otp')" :hint="t('auth.otpHint')" name="otp">
          <UInput v-model="otp" inputmode="numeric" autocomplete="one-time-code" required autofocus class="w-full" />
        </UFormField>

        <UAlert v-if="error" role="alert" color="error" variant="subtle" :description="error" />

        <UAlert v-if="needsVerification" role="alert" color="warning" variant="subtle" :description="t('auth.verify.needed')">
          <template #actions>
            <UButton size="xs" variant="soft" @click="resendVerification">{{ t('auth.verify.resend') }}</UButton>
          </template>
        </UAlert>

        <UButton type="submit" :loading="loading" block>
          {{ t('auth.signIn') }}
        </UButton>

        <div class="flex flex-col gap-1.5 text-center">
          <NuxtLink to="/forgot-password" class="text-sm text-muted hover:text-default">
            {{ t('auth.forgotPassword') }}
          </NuxtLink>
          <NuxtLink to="/register" class="text-sm text-muted hover:text-default">
            {{ t('auth.register.cta') }}
          </NuxtLink>
        </div>
      </form>

      <div class="mt-4 flex justify-center gap-3 text-xs text-muted">
        <NuxtLink to="/legal/terms" class="hover:text-default">{{ t('legal.terms.title') }}</NuxtLink>
        <span>·</span>
        <NuxtLink to="/legal/privacy" class="hover:text-default">{{ t('legal.privacy.title') }}</NuxtLink>
      </div>
    </UCard>
  </div>
</template>
