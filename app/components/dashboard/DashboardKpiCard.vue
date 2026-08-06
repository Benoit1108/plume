<script setup lang="ts">
/**
 * Carte KPI du tableau de bord : libellé, valeur (déjà formatée) et sous-titre.
 *
 * `emphasis` (revue design) : les six indicateurs avaient exactement le même poids, donc aucun ne
 * disait « regarde-moi d'abord ». Deux d'entre eux portent la thèse du produit — est-ce qu'on
 * obtient des réponses, et est-ce qu'on transforme — et prennent le pas ; les autres restent
 * secondaires, à lire ensuite.
 */
withDefaults(defineProps<{ label: string, value: string, hint: string, emphasis?: boolean }>(), {
  emphasis: false,
})
</script>

<template>
  <div
    class="border rounded-xl p-4"
    :class="emphasis
      ? 'border-primary/30 bg-primary/5 sm:col-span-1 lg:col-span-2'
      : 'border-default bg-elevated/40'"
  >
    <p class="text-xs font-semibold uppercase tracking-wide" :class="emphasis ? 'text-primary' : 'text-dimmed'">
      {{ label }}
    </p>
    <p class="mt-1 font-serif font-semibold tabular-nums" :class="emphasis ? 'text-5xl' : 'text-3xl'">
      {{ value }}
    </p>
    <p class="text-xs text-muted mt-1">{{ hint }}</p>
  </div>
</template>
