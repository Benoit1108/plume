<script setup lang="ts">
/** Bandeau d'erreur de chargement homogène (revue globale front P1) : avec `retry:false`, un seul
 *  échec réseau laissait des écrans blancs ou des « états vides » trompeurs. Montrer + réessayer. */
defineProps<{ message?: string }>()
const emit = defineEmits<{ retry: [] }>()
const { t } = useI18n()
</script>

<template>
  <UAlert
    color="error"
    variant="subtle"
    icon="i-lucide-triangle-alert"
    :title="message ?? t('common.loadError')"
    :description="t('common.loadErrorHint')"
  >
    <template #actions>
      <UButton size="xs" color="error" variant="soft" @click="emit('retry')">
        {{ t('common.retry') }}
      </UButton>
    </template>
  </UAlert>
</template>
