<script setup lang="ts">
const { t } = useI18n()
const titleWords = computed(() => t('landing.hero.title').split(' '))
</script>

<template>
  <section class="relative overflow-hidden">
    <div class="ink-bloom pointer-events-none absolute inset-x-0 top-0 h-[520px]" data-parallax="0.12" aria-hidden="true" />
    <LandingFeather
      class="pointer-events-none absolute top-20 -right-16 w-[340px] md:w-[560px] text-primary opacity-[0.18] will-change-transform"
      data-parallax="-0.22"
      data-mouse="16"
      data-rot="8"
      style="transform: rotate(8deg)"
    />

    <div class="relative z-10 max-w-4xl mx-auto px-4 sm:px-8 pt-24 pb-8 text-center">
      <p class="enter font-mono text-xs uppercase tracking-[0.2em] text-primary" style="animation-delay: 0.05s">{{ t('landing.hero.eyebrow') }}</p>
      <div class="relative mt-4 inline-block">
        <h1 class="font-serif text-4xl sm:text-6xl font-semibold text-balance leading-[1.05]">
          <span v-for="(w, i) in titleWords" :key="i" class="word" :style="{ animationDelay: `${0.12 + i * 0.06}s` }">{{ w }}</span>
        </h1>
        <svg
          class="ink-draw pointer-events-none absolute left-1/2 top-full w-48 sm:w-64 -translate-x-1/2 mt-5 text-primary/70"
          viewBox="0 0 320 28"
          fill="none"
          aria-hidden="true"
        >
          <path d="M8 18C70 6 138 5 184 13S276 22 312 9" stroke="currentColor" stroke-width="3" stroke-linecap="round" pathLength="1" />
        </svg>
      </div>
      <p class="enter mt-12 text-lg text-muted max-w-2xl mx-auto text-pretty" style="animation-delay: 0.5s">{{ t('landing.hero.subtitle') }}</p>
      <LandingCtaButtons class="enter mt-8" style="animation-delay: 0.6s" />
      <p class="enter mt-3 text-xs text-dimmed" style="animation-delay: 0.7s">{{ t('landing.hero.trialNote') }} · {{ t('landing.hero.demoNote') }}</p>
    </div>
  </section>
</template>

<style scoped>
/* Entrée orchestrée du hero + révélation mot à mot du titre. */
.enter { opacity: 0; animation: enter 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
@keyframes enter { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }

/* margin-inline-end remplace l'espace : inline-block avale l'espace blanc entre les mots. */
.word { display: inline-block; opacity: 0; margin-inline-end: 0.25em; animation: wordin 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) both; }
@keyframes wordin { from { opacity: 0; transform: translateY(18px); filter: blur(6px); } to { opacity: 1; transform: none; filter: none; } }

@media (prefers-reduced-motion: reduce) {
  .enter,
  .word { animation: none !important; opacity: 1 !important; transform: none !important; filter: none !important; }
}
</style>
