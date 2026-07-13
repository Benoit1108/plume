<script setup lang="ts">
const { t } = useI18n()
const auth = useAuthStore()
</script>

<template>
  <UContainer class="py-16">
    <PlumeMark :size="30" />
    <h1 class="mt-6 font-serif text-3xl font-semibold">
      {{ t('home.welcome') }}
    </h1>
    <p class="mt-2 text-muted">
      {{ t('home.connectedAs', { email: auth.email }) }}
    </p>
    <p class="mt-4 text-sm text-muted">
      {{ t('home.repertoireSoon') }}
    </p>
    <UButton class="mt-6" color="neutral" variant="outline" @click="auth.logout()">
      {{ t('home.logout') }}
    </UButton>
  </UContainer>
</template>
