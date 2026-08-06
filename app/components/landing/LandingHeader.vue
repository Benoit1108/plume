<script setup lang="ts">
const { t } = useI18n()
</script>

<template>
  <header class="sticky top-0 z-40 h-16 flex items-center gap-4 px-4 sm:px-8 border-b border-default bg-default/80 backdrop-blur">
    <!-- Lien d'évitement (WCAG 2.4.1) : invisible jusqu'à la tabulation, il permet de sauter
         l'en-tête collant et les liens de section pour atteindre le contenu. -->
    <a
      href="#main"
      class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-3 focus:z-50 focus:rounded-md focus:bg-elevated focus:px-3 focus:py-2 focus:text-sm focus:outline-2 focus:outline-primary"
    >{{ t('nav.skipToContent') }}</a>
    <NuxtLink to="/" class="inline-flex rounded-md focus-visible:outline-2 focus-visible:outline-primary" :aria-label="t('nav.home')">
      <PlumeMark :size="24" />
    </NuxtLink>
    <nav class="hidden md:flex items-center gap-5 ml-2 text-sm">
      <a
        v-for="section in ['how', 'features', 'pricing']"
        :key="section"
        :href="`#${section}`"
        class="text-muted hover:text-default transition-colors rounded-sm focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary"
      >{{ t(`landing.${section === 'how' ? 'how' : section}.eyebrow`) }}</a>
    </nav>
    <div class="flex-1" />
    <LocaleSwitcher />
    <ThemeToggle />
    <!-- « Se connecter » masqué sous sm : à 390 px les cinq éléments ne tiennent pas et faisaient
         déborder la page. Le lien reste atteignable dans le pied de page. -->
    <UButton to="/login" color="neutral" variant="ghost" size="sm" class="hidden sm:inline-flex">{{ t('landing.nav.login') }}</UButton>
    <UButton to="/register" size="sm">{{ t('landing.nav.signup') }}</UButton>
  </header>
</template>
