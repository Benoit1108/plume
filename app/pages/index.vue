<script setup lang="ts">
// Vitrine publique (V2) — orchestrateur mince. Racine « / » : page marketing pour les visiteurs,
// redirigée vers l'app (l'écran du quotidien) dès qu'on est connecté (décision M1.3 n°5).
// Sections découpées par domaine (components/landing/*) ; mouvement ambiant dans useLandingMotion.
definePageMeta({ layout: false })

const { t } = useI18n()
const auth = useAuthStore()
if (auth.isAuthenticated) {
  await navigateTo('/today', { replace: true })
}

// Surcharge côté client (onglet + bascule FR/EN) ; l'HTML généré porte déjà le head statique
// de nuxt.config, seul lu par les robots et les aperçus de lien.
useSeoMeta({
  title: () => t('seo.landingTitle'),
  description: () => t('seo.landingDescription'),
  ogTitle: () => t('seo.landingTitle'),
  ogDescription: () => t('seo.landingDescription'),
})

const root = ref<HTMLElement>()
const motes = ref<HTMLCanvasElement>()
const progress = ref<HTMLElement>()

useLandingMotion({ root, canvas: motes, progress })
</script>

<template>
  <div ref="root" class="landing-page relative min-h-screen bg-default text-default flex flex-col">
    <div
      ref="progress"
      class="fixed top-0 left-0 h-0.5 w-0 z-[60]"
      style="background: linear-gradient(90deg, var(--color-plume-600), var(--color-plume-300))"
      aria-hidden="true"
    />
    <canvas ref="motes" class="pointer-events-none fixed inset-0 z-0" aria-hidden="true" />

    <LandingHeader />

    <main id="main" class="relative z-10 flex-1">
      <LandingHero />
      <LandingShowcase />
      <LandingHowItWorks />
      <LandingFeatures />
      <LandingAudience />
      <LandingPricing />
      <LandingCta />
    </main>

    <LandingFooter />
  </div>
</template>
