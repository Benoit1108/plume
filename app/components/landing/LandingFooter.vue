<script setup lang="ts">
const { t } = useI18n()
</script>

<template>
  <footer class="relative z-10 border-t border-default px-4 sm:px-8 py-6 text-xs text-muted flex flex-wrap items-center gap-x-4 gap-y-2">
    <span>© {{ new Date().getFullYear() }} Plume</span>
    <div class="flex-1" />
    <NuxtLink to="/legal/terms" class="hover:text-default">{{ t('legal.terms.title') }}</NuxtLink>
    <NuxtLink to="/legal/privacy" class="hover:text-default">{{ t('legal.privacy.title') }}</NuxtLink>
    <NuxtLink to="/login" class="hover:text-default">{{ t('landing.nav.login') }}</NuxtLink>
  </footer>
</template>
