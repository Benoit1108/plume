<script setup lang="ts">
const { t } = useI18n()
</script>

<template>
  <section class="relative overflow-hidden border-t border-default">
    <div class="ink-bloom pointer-events-none absolute inset-0" aria-hidden="true" />
    <!-- Centrage par le conteneur : la parallaxe pilote le transform de la plume (évite le conflit
         avec un centrage par translate). -->
    <div class="pointer-events-none absolute inset-0 grid place-items-center overflow-hidden">
      <LandingFeather
        class="w-[520px] text-primary opacity-[0.06] will-change-transform"
        data-parallax="-0.15"
        data-rot="-6"
        style="transform: rotate(-6deg)"
      />
    </div>
    <div class="relative z-10 max-w-3xl mx-auto px-4 sm:px-8 py-16 sm:py-20 text-center">
      <h2 class="reveal font-serif text-3xl sm:text-4xl font-semibold text-balance">{{ t('landing.cta.title') }}</h2>
      <p class="reveal mt-4 text-muted max-w-xl mx-auto text-pretty">{{ t('landing.cta.text') }}</p>
      <LandingCtaButtons class="reveal mt-8" />
    </div>
  </section>
</template>
