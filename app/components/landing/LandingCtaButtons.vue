<script setup lang="ts">
// Paire de boutons d'action réutilisée par le hero et le bandeau CTA.
const { t } = useI18n()
const { entering, enterDemo } = useDemoLogin()
</script>

<template>
  <div class="flex items-center justify-center gap-3 flex-wrap">
    <UButton to="/register" size="lg" icon="i-lucide-arrow-right" trailing>{{ t('landing.hero.cta') }}</UButton>
    <UButton size="lg" color="neutral" variant="outline" icon="i-lucide-play" :loading="entering" @click="enterDemo">{{ t('landing.hero.demoCta') }}</UButton>
  </div>
</template>
