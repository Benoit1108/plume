<script setup lang="ts">
const { locale, locales, setLocale, t } = useI18n()
const colorMode = useColorMode()
const config = useRuntimeConfig()

function toggleTheme() {
  colorMode.preference = colorMode.value === 'dark' ? 'light' : 'dark'
}
</script>

<template>
  <UApp>
    <UContainer class="py-16 space-y-6">
      <h1 class="text-3xl font-bold">🪶 Plume</h1>
      <p class="text-lg">{{ t('tagline') }}</p>

      <div class="flex flex-wrap items-center gap-2">
        <UButton
          v-for="l in locales"
          :key="l.code"
          :variant="l.code === locale ? 'solid' : 'outline'"
          @click="setLocale(l.code)"
        >
          {{ l.name }}
        </UButton>

        <UButton color="neutral" variant="subtle" @click="toggleTheme">
          {{ t('theme.toggle') }}
        </UButton>
      </div>

      <p class="text-sm text-muted">
        API : {{ config.public.apiBase }} — squelette M0 (voir <code>docs/ROADMAP.md</code>).
      </p>
    </UContainer>
  </UApp>
</template>
