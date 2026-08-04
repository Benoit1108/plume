<script setup lang="ts">
/** Carte KPI du tableau de bord : libellé, valeur (déjà formatée) et sous-titre. */
defineProps<{ label: string, value: string, hint: string }>()
</script>

<template>
  <div class="border border-default rounded-xl p-4 bg-elevated/40">
    <p class="text-xs text-dimmed font-semibold uppercase tracking-wide">{{ label }}</p>
    <p class="mt-1 font-serif text-3xl font-semibold tabular-nums">{{ value }}</p>
    <p class="text-xs text-muted mt-1">{{ hint }}</p>
  </div>
</template>
