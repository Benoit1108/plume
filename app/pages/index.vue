<script setup lang="ts">
// Vitrine publique (V2). Racine « / » : page marketing pour les visiteurs, redirigée vers l'app
// (l'écran du quotidien) dès qu'on est connecté (décision M1.3 n°5).
definePageMeta({ layout: false })

const { t } = useI18n()
const auth = useAuthStore()

if (auth.isAuthenticated) {
  await navigateTo('/today', { replace: true })
}

const features = ['pipeline', 'replies', 'drafting', 'sourcing', 'dashboard'] as const
const included = ['pipeline', 'drafting', 'mailbox', 'sourcing', 'dashboard', 'support'] as const
</script>

<template>
  <div class="min-h-screen bg-default text-default flex flex-col">
    <!-- En-tête -->
    <header class="h-16 flex items-center gap-3 px-4 sm:px-8 border-b border-default">
      <NuxtLink to="/" class="flex items-center gap-2" :aria-label="t('nav.home')">
        <PlumeMark :size="22" />
        <span class="font-serif text-lg font-semibold">Plume</span>
      </NuxtLink>
      <div class="flex-1" />
      <LocaleSwitcher />
      <ThemeToggle />
      <UButton to="/login" color="neutral" variant="ghost" size="sm">{{ t('landing.nav.login') }}</UButton>
      <UButton to="/register" size="sm">{{ t('landing.nav.signup') }}</UButton>
    </header>

    <main class="flex-1">
      <!-- Hero -->
      <section class="max-w-3xl mx-auto px-4 sm:px-8 pt-16 pb-14 text-center">
        <p class="font-mono text-xs uppercase tracking-widest text-primary">{{ t('landing.hero.eyebrow') }}</p>
        <h1 class="mt-3 font-serif text-4xl sm:text-5xl font-semibold text-balance leading-tight">{{ t('landing.hero.title') }}</h1>
        <p class="mt-5 text-lg text-muted max-w-2xl mx-auto">{{ t('landing.hero.subtitle') }}</p>
        <div class="mt-8 flex items-center justify-center gap-3 flex-wrap">
          <UButton to="/register" size="lg" icon="i-lucide-arrow-right" trailing>{{ t('landing.hero.cta') }}</UButton>
          <UButton to="/login" size="lg" color="neutral" variant="outline">{{ t('landing.nav.login') }}</UButton>
        </div>
        <p class="mt-3 text-xs text-dimmed">{{ t('landing.hero.trialNote') }}</p>
      </section>

      <!-- Fonctionnalités -->
      <section class="max-w-5xl mx-auto px-4 sm:px-8 py-8">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div v-for="f in features" :key="f" class="border border-default rounded-xl p-5 bg-elevated/40">
            <UIcon :name="t(`landing.features.${f}.icon`)" class="size-6 text-primary" aria-hidden="true" />
            <h2 class="mt-3 font-semibold">{{ t(`landing.features.${f}.title`) }}</h2>
            <p class="mt-1 text-sm text-muted">{{ t(`landing.features.${f}.text`) }}</p>
          </div>
        </div>
      </section>

      <!-- Tarifs -->
      <section class="max-w-md mx-auto px-4 sm:px-8 py-12">
        <div class="border border-primary/40 rounded-2xl p-6 bg-elevated/40">
          <div class="flex items-baseline justify-between">
            <h2 class="font-serif text-xl font-semibold">{{ t('landing.pricing.planName') }}</h2>
            <UBadge color="primary" variant="soft">{{ t('landing.pricing.badge') }}</UBadge>
          </div>
          <p class="mt-4">
            <span class="font-serif text-4xl font-semibold tabular-nums">{{ t('landing.pricing.monthly') }}</span>
            <span class="text-muted text-sm"> {{ t('landing.pricing.perMonth') }}</span>
          </p>
          <p class="text-xs text-muted mt-1">{{ t('landing.pricing.annual') }}</p>
          <ul class="mt-5 flex flex-col gap-2 text-sm">
            <li v-for="item in included" :key="item" class="flex items-start gap-2">
              <UIcon name="i-lucide-check" class="size-4 text-primary mt-0.5 shrink-0" aria-hidden="true" />
              <span>{{ t(`landing.pricing.included.${item}`) }}</span>
            </li>
          </ul>
          <UButton to="/register" block size="lg" class="mt-6">{{ t('landing.hero.cta') }}</UButton>
          <p class="text-center text-xs text-dimmed mt-2">{{ t('landing.hero.trialNote') }}</p>
        </div>
      </section>
    </main>

    <!-- Pied de page -->
    <footer class="border-t border-default px-4 sm:px-8 py-6 text-xs text-muted flex flex-wrap items-center gap-x-4 gap-y-2">
      <span>© {{ new Date().getFullYear() }} Plume</span>
      <div class="flex-1" />
      <NuxtLink to="/legal/terms" class="hover:text-default">{{ t('legal.terms.title') }}</NuxtLink>
      <NuxtLink to="/legal/privacy" class="hover:text-default">{{ t('legal.privacy.title') }}</NuxtLink>
      <NuxtLink to="/login" class="hover:text-default">{{ t('landing.nav.login') }}</NuxtLink>
    </footer>
  </div>
</template>
