<script setup lang="ts">
definePageMeta({ layout: false })

const { t } = useI18n()
const route = useRoute()
// Deux documents pour l'instant : CGU et politique de confidentialité.
const doc = computed(() => (route.params.doc === 'privacy' ? 'privacy' : 'terms'))
</script>

<template>
  <div class="min-h-screen p-6">
    <div class="max-w-2xl mx-auto">
      <div class="flex justify-between items-center">
        <PlumeMark :size="28" />
        <div class="flex gap-2">
          <LocaleSwitcher />
          <ThemeToggle />
        </div>
      </div>

      <article class="mt-8 flex flex-col gap-4">
        <h1 class="text-2xl font-semibold">{{ t(`legal.${doc}.title`) }}</h1>
        <UAlert color="warning" variant="subtle" :description="t('legal.draft')" />
        <p class="text-muted whitespace-pre-line">{{ t(`legal.${doc}.body`) }}</p>
      </article>

      <NuxtLink to="/login" class="text-sm text-muted hover:text-default mt-8 inline-block">
        {{ t('auth.backToLogin') }}
      </NuxtLink>
    </div>
  </div>
</template>
