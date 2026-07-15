<script setup lang="ts">
/**
 * Gabarit de largeur partagé — « doctrine à deux largeurs » (+ largeur formulaire).
 * Évite la dérive de `max-w-*` d'une page à l'autre (cf. état des lieux visuel).
 *  - reading : pages mono-colonne (aujourd'hui, fiches, modèles) ;
 *  - atelier : pages de données larges (tableau de bord, pistes, répertoire) ;
 *  - form    : formulaires de saisie (mesure de saisie confortable).
 */
const props = withDefaults(defineProps<{ width?: 'reading' | 'atelier' | 'form' }>(), {
  width: 'reading',
})

const widthClass = computed(() => ({
  reading: 'max-w-[880px]',
  atelier: 'max-w-[1120px]',
  form: 'max-w-2xl',
}[props.width]))
</script>

<template>
  <UContainer :class="['py-8', widthClass]">
    <slot />
  </UContainer>
</template>
