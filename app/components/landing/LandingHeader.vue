<script setup lang="ts">
const { t } = useI18n()
</script>

<template>
  <header class="sticky top-0 z-40 h-16 flex items-center gap-4 px-4 sm:px-8 border-b border-default bg-default/80 backdrop-blur">
    <NuxtLink to="/" class="inline-flex rounded-md focus-visible:outline-2 focus-visible:outline-primary" :aria-label="t('nav.home')">
      <PlumeMark :size="24" />
    </NuxtLink>
    <nav class="hidden md:flex items-center gap-5 ml-2 text-sm">
      <a href="#how" class="text-muted hover:text-default transition-colors">{{ t('landing.how.eyebrow') }}</a>
      <a href="#features" class="text-muted hover:text-default transition-colors">{{ t('landing.features.eyebrow') }}</a>
      <a href="#pricing" class="text-muted hover:text-default transition-colors">{{ t('landing.pricing.eyebrow') }}</a>
    </nav>
    <div class="flex-1" />
    <LocaleSwitcher />
    <ThemeToggle />
    <UButton to="/login" color="neutral" variant="ghost" size="sm">{{ t('landing.nav.login') }}</UButton>
    <UButton to="/register" size="sm">{{ t('landing.nav.signup') }}</UButton>
  </header>
</template>
