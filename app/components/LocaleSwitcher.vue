<script setup lang="ts">
const { locale, locales, setLocale, t } = useI18n()

const items = computed(() =>
  locales.value.map(l => ({ value: l.code, label: l.name ?? l.code })),
)
</script>

<template>
  <USelect
    :model-value="locale"
    :items="items"
    :aria-label="t('locale.label')"
    color="neutral"
    variant="ghost"
    size="sm"
    @update:model-value="(code) => setLocale(code as typeof locale)"
  />
</template>
