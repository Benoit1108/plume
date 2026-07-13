<script setup lang="ts">
defineProps<{
  title: string
  description: string
  confirmLabel?: string
  danger?: boolean
}>()

const open = defineModel<boolean>('open', { default: false })
const emit = defineEmits<{ confirm: [] }>()

const { t } = useI18n()

function confirm(): void {
  emit('confirm')
  open.value = false
}
</script>

<template>
  <UModal v-model:open="open" :title="title" :description="description">
    <template #footer>
      <div class="flex gap-2 justify-end w-full">
        <UButton color="neutral" variant="ghost" @click="() => { open = false }">
          {{ t('actions.cancel') }}
        </UButton>
        <UButton :color="danger ? 'error' : 'primary'" @click="confirm">
          {{ confirmLabel ?? t('actions.confirm') }}
        </UButton>
      </div>
    </template>
  </UModal>
</template>
