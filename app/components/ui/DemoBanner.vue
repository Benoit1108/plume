<script setup lang="ts">
/**
 * Bandeau « mode démonstration » (vitrine V2) : visible pour une session de démo (ROLE_DEMO).
 * Rappelle que les données sont fictives et éphémères, et invite à créer un vrai compte.
 */
const { t } = useI18n()
const auth = useAuthStore()
</script>

<template>
  <UAlert
    v-if="auth.isDemo"
    color="info"
    variant="subtle"
    icon="i-lucide-flask-conical"
    :title="t('demo.banner.title')"
    :description="t('demo.banner.hint')"
    class="rounded-none border-x-0 border-t-0"
  >
    <template #actions>
      <UButton size="xs" color="info" to="/register">{{ t('demo.banner.cta') }}</UButton>
    </template>
  </UAlert>
</template>
