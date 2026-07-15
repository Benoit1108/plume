<script setup lang="ts">
/**
 * En-tête de page partagé : lien retour optionnel, sur-titre (« eyebrow »),
 * titre en serif, et actions ancrées à droite (empilées sous le titre en mobile).
 * Unifie la mise en forme des en-têtes et supprime les boutons d'action « flottants ».
 *
 * Slots :
 *  - #title-extra : contenu inline après le titre (badges de statut, etc.) ;
 *  - #subtitle    : ligne(s) sous le titre (méta, intro) ;
 *  - #actions     : boutons d'action (ancrés en haut à droite).
 */
defineProps<{
  eyebrow?: string
  title?: string
  backTo?: string
  backLabel?: string
}>()
</script>

<template>
  <div class="mb-6">
    <UButton
      v-if="backTo"
      variant="link"
      icon="i-lucide-arrow-left"
      :to="backTo"
      class="px-0 mb-2"
    >
      {{ backLabel }}
    </UButton>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
      <div class="min-w-0">
        <p v-if="eyebrow" class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">
          {{ eyebrow }}
        </p>
        <div class="flex items-center gap-2 flex-wrap" :class="{ 'mt-1': eyebrow }">
          <h1 class="font-serif text-3xl font-semibold leading-tight">{{ title }}</h1>
          <slot name="title-extra" />
        </div>
        <slot name="subtitle" />
      </div>

      <div v-if="$slots.actions" class="flex gap-2 flex-wrap shrink-0 sm:justify-end sm:pt-1">
        <slot name="actions" />
      </div>
    </div>
  </div>
</template>
