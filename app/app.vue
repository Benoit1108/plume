<script setup lang="ts">
// Réaligne les attributs <html> (lang/dir) sur la locale active : le `lang` statique de
// nuxt.config est en français (locale par défaut, baké dans l'HTML) et doit suivre la bascule
// FR/EN au runtime — sinon un lecteur d'écran lit l'anglais avec une voix française (WCAG 3.1.1).
const { locale } = useI18n()
useHead(() => ({ htmlAttrs: { lang: locale.value } }))
</script>

<template>
  <UApp>
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>
