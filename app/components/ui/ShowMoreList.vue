<script setup lang="ts" generic="T">
/**
 * Liste plafonnée avec dépliage à la demande (lot « densité ») : on n'affiche que les `initial`
 * premiers éléments et un bouton qui annonce COMBIEN il en reste. Réutilisable partout où une
 * liste de travail peut s'allonger (sessions actives, etc.).
 */
const props = withDefaults(defineProps<{
  items: T[]
  /** Plafond avant dépliage. */
  initial?: number
  /** Clé de rendu stable — sinon l'index (suffisant pour une liste qui ne se réordonne pas). */
  itemKey?: (item: T, index: number) => string | number
  listClass?: string
}>(), {
  initial: 5,
  itemKey: undefined,
  listClass: 'flex flex-col gap-2',
})

defineSlots<{ default: (props: { item: T, index: number }) => unknown }>()

const { t } = useI18n()
const listId = useId()
const { visible, expanded, hiddenCount, toggle } = useShowMore<T>(() => props.items, () => props.initial)
</script>

<template>
  <div class="flex flex-col gap-2">
    <ul :id="listId" :class="listClass">
      <li v-for="(item, index) in visible" :key="itemKey ? itemKey(item, index) : index">
        <slot :item="item" :index="index" />
      </li>
    </ul>

    <UButton
      v-if="hiddenCount > 0"
      size="xs"
      variant="ghost"
      color="neutral"
      class="self-start"
      :icon="expanded ? 'i-lucide-chevron-up' : 'i-lucide-chevron-down'"
      :aria-expanded="expanded"
      :aria-controls="listId"
      @click="toggle"
    >
      {{ expanded ? t('common.showLess') : t('common.showMore', { count: hiddenCount }, hiddenCount) }}
    </UButton>
  </div>
</template>
