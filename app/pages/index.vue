<script setup lang="ts">
// Vitrine publique (V2). Racine « / » : page marketing pour les visiteurs, redirigée vers l'app
// (l'écran du quotidien) dès qu'on est connecté (décision M1.3 n°5).
definePageMeta({ layout: false })

const { t } = useI18n()
const auth = useAuthStore()
const toast = useToast()

if (auth.isAuthenticated) {
  await navigateTo('/today', { replace: true })
}

const features = ['pipeline', 'replies', 'drafting', 'sourcing', 'dashboard'] as const
const included = ['pipeline', 'drafting', 'mailbox', 'sourcing', 'dashboard', 'support'] as const
const steps = ['find', 'write', 'track'] as const
const audience = ['editorial', 'audiovisual', 'technical'] as const

// Aperçu produit (écran « Aujourd'hui ») — organisations FICTIVES, purement illustratives.
const previewRows = [
  { name: 'Éditions Margelle', status: 'due', color: 'warning', icon: 'i-lucide-clock' },
  { name: 'Studio Bleu Nuit', status: 'replied', color: 'success', icon: 'i-lucide-mail-check' },
  { name: 'Agence Verba', status: 'dormant', color: 'neutral', icon: 'i-lucide-moon' },
] as const

// « Essayer la démo » : l'API monte un compte éphémère pré-rempli et nous connecte directement.
const enteringDemo = ref(false)
async function tryDemo() {
  if (enteringDemo.value) return
  enteringDemo.value = true
  try {
    await auth.enterDemo()
    await navigateTo('/today')
  }
  catch {
    toast.add({ title: t('landing.demo.error'), color: 'error' })
  }
  finally {
    enteringDemo.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-default text-default flex flex-col">
    <!-- En-tête -->
    <header class="sticky top-0 z-40 h-16 flex items-center gap-3 px-4 sm:px-8 border-b border-default bg-default/80 backdrop-blur">
      <NuxtLink to="/" class="inline-flex rounded-md focus-visible:outline-2 focus-visible:outline-primary" :aria-label="t('nav.home')">
        <PlumeMark :size="24" />
      </NuxtLink>
      <div class="flex-1" />
      <LocaleSwitcher />
      <ThemeToggle />
      <UButton to="/login" color="neutral" variant="ghost" size="sm">{{ t('landing.nav.login') }}</UButton>
      <UButton to="/register" size="sm">{{ t('landing.nav.signup') }}</UButton>
    </header>

    <main class="flex-1">
      <!-- Hero -->
      <section class="relative overflow-hidden">
        <div class="ink-bloom pointer-events-none absolute inset-x-0 top-0 h-[440px]" aria-hidden="true" />

        <div class="relative max-w-3xl mx-auto px-4 sm:px-8 pt-20 pb-8 text-center rise-stagger">
          <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.hero.eyebrow') }}</p>
          <div class="relative mt-4 inline-block">
            <h1 class="font-serif text-4xl sm:text-6xl font-semibold text-balance leading-[1.05]">{{ t('landing.hero.title') }}</h1>
            <svg
              class="ink-draw pointer-events-none absolute left-1/2 bottom-0 w-56 sm:w-72 -translate-x-1/2 translate-y-3 text-primary/80"
              viewBox="0 0 320 40"
              fill="none"
              aria-hidden="true"
            >
              <path d="M8 27C64 9 132 8 180 21S276 33 312 13" stroke="currentColor" stroke-width="3" stroke-linecap="round" pathLength="1" />
            </svg>
          </div>
          <p class="mt-6 text-lg text-muted max-w-2xl mx-auto text-pretty">{{ t('landing.hero.subtitle') }}</p>
          <div class="mt-8 flex items-center justify-center gap-3 flex-wrap">
            <UButton to="/register" size="lg" icon="i-lucide-arrow-right" trailing>{{ t('landing.hero.cta') }}</UButton>
            <UButton size="lg" color="neutral" variant="outline" icon="i-lucide-play" :loading="enteringDemo" @click="tryDemo">{{ t('landing.hero.demoCta') }}</UButton>
          </div>
          <p class="mt-3 text-xs text-dimmed">{{ t('landing.hero.trialNote') }} · {{ t('landing.hero.demoNote') }}</p>
        </div>

        <!-- Aperçu produit : l'écran « Aujourd'hui » (données fictives, mêmes tokens que l'app) -->
        <div class="relative max-w-2xl mx-auto px-4 sm:px-8 pb-16 rise" style="animation-delay: 0.3s">
          <div class="rounded-2xl border border-default bg-elevated shadow-2xl shadow-primary/10 overflow-hidden">
            <div class="flex items-center gap-1.5 h-10 px-4 border-b border-default bg-muted/50">
              <span class="size-2.5 rounded-full bg-default/60" aria-hidden="true" />
              <span class="size-2.5 rounded-full bg-default/40" aria-hidden="true" />
              <span class="size-2.5 rounded-full bg-default/25" aria-hidden="true" />
              <span class="ml-2 font-mono text-xs text-dimmed">plume · {{ t('landing.preview.title') }}</span>
            </div>
            <div class="p-5 text-left">
              <p class="text-sm text-muted">{{ t('landing.preview.caption') }}</p>
              <ul class="mt-4 flex flex-col gap-2">
                <li
                  v-for="row in previewRows"
                  :key="row.name"
                  class="flex items-center gap-3 rounded-lg border border-default bg-default px-3 py-2.5"
                >
                  <span class="grid size-8 place-items-center rounded-full bg-muted text-muted shrink-0">
                    <UIcon :name="row.icon" class="size-4" aria-hidden="true" />
                  </span>
                  <span class="font-medium truncate">{{ row.name }}</span>
                  <div class="flex-1" />
                  <UBadge :color="row.color" variant="soft" size="sm">{{ t(`landing.preview.status.${row.status}`) }}</UBadge>
                </li>
              </ul>
              <div class="mt-5">
                <div class="flex items-center justify-between text-xs text-muted">
                  <span>{{ t('landing.preview.goal') }}</span>
                  <span class="font-mono tabular-nums text-default">5 / 8</span>
                </div>
                <div class="mt-1.5 h-2 rounded-full bg-muted overflow-hidden">
                  <div class="h-full rounded-full bg-primary grow-x" style="width: 62.5%; animation-delay: 0.5s" />
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Comment ça marche — séquence en 3 gestes (numérotation = ordre réel) -->
      <section class="bg-muted/40 border-y border-default">
        <div class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20">
          <div class="max-w-xl mx-auto text-center">
            <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.how.eyebrow') }}</p>
            <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.how.title') }}</h2>
          </div>
          <ol class="mt-12 grid gap-8 sm:grid-cols-3">
            <li v-for="(s, i) in steps" :key="s" class="relative">
              <div class="flex items-center gap-3">
                <span class="font-mono text-sm text-primary tabular-nums">0{{ i + 1 }}</span>
                <span class="h-px flex-1 bg-default" aria-hidden="true" />
                <UIcon :name="t(`landing.how.${s}.icon`)" class="size-5 text-primary" aria-hidden="true" />
              </div>
              <h3 class="mt-4 font-serif text-xl font-semibold">{{ t(`landing.how.${s}.title`) }}</h3>
              <p class="mt-2 text-sm text-muted text-pretty">{{ t(`landing.how.${s}.text`) }}</p>
            </li>
          </ol>
        </div>
      </section>

      <!-- Fonctionnalités -->
      <section class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20">
        <div class="max-w-xl mx-auto text-center">
          <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.features.eyebrow') }}</p>
          <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.features.title') }}</h2>
        </div>
        <div class="mt-12 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="f in features"
            :key="f"
            class="border border-default rounded-xl p-5 bg-elevated/40 motion-safe:transition motion-safe:duration-200 hover:border-primary/40 hover:-translate-y-0.5"
          >
            <UIcon :name="t(`landing.features.${f}.icon`)" class="size-6 text-primary" aria-hidden="true" />
            <h3 class="mt-3 font-serif text-lg font-semibold">{{ t(`landing.features.${f}.title`) }}</h3>
            <p class="mt-1 text-sm text-muted text-pretty">{{ t(`landing.features.${f}.text`) }}</p>
          </div>
        </div>
      </section>

      <!-- Pour qui -->
      <section class="bg-muted/40 border-y border-default">
        <div class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20">
          <div class="max-w-xl mx-auto text-center">
            <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.audience.eyebrow') }}</p>
            <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.audience.title') }}</h2>
          </div>
          <div class="mt-12 grid gap-4 sm:grid-cols-3">
            <div v-for="a in audience" :key="a" class="rounded-xl border border-default bg-default p-5">
              <UIcon :name="t(`landing.audience.${a}.icon`)" class="size-6 text-primary" aria-hidden="true" />
              <h3 class="mt-3 font-serif text-lg font-semibold">{{ t(`landing.audience.${a}.title`) }}</h3>
              <p class="mt-1 text-sm text-muted text-pretty">{{ t(`landing.audience.${a}.text`) }}</p>
            </div>
          </div>
        </div>
      </section>

      <!-- Tarifs -->
      <section class="max-w-5xl mx-auto px-4 sm:px-8 py-16 sm:py-20">
        <div class="max-w-xl mx-auto text-center">
          <p class="font-mono text-xs uppercase tracking-[0.2em] text-primary">{{ t('landing.pricing.eyebrow') }}</p>
          <h2 class="mt-3 font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.pricing.title') }}</h2>
        </div>
        <div class="mt-10 max-w-md mx-auto">
          <div class="border border-primary/40 rounded-2xl p-6 bg-elevated/40">
            <div class="flex items-baseline justify-between">
              <h3 class="font-serif text-xl font-semibold">{{ t('landing.pricing.planName') }}</h3>
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
            <p class="text-center text-xs text-dimmed mt-2">{{ t('landing.pricing.trust') }}</p>
          </div>
        </div>
      </section>

      <!-- Bandeau d'appel à l'action -->
      <section class="relative overflow-hidden border-t border-default">
        <div class="ink-bloom pointer-events-none absolute inset-0" aria-hidden="true" />
        <div class="relative max-w-3xl mx-auto px-4 sm:px-8 py-16 sm:py-20 text-center">
          <h2 class="font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.cta.title') }}</h2>
          <p class="mt-4 text-muted max-w-xl mx-auto text-pretty">{{ t('landing.cta.text') }}</p>
          <div class="mt-8 flex items-center justify-center gap-3 flex-wrap">
            <UButton to="/register" size="lg" icon="i-lucide-arrow-right" trailing>{{ t('landing.hero.cta') }}</UButton>
            <UButton size="lg" color="neutral" variant="outline" icon="i-lucide-play" :loading="enteringDemo" @click="tryDemo">{{ t('landing.hero.demoCta') }}</UButton>
          </div>
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
