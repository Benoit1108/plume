<script setup lang="ts">
import type { Contact, ContactInput } from '~/types/directory'

const props = defineProps<{
  initial?: Partial<Contact>
  submitting?: boolean
  submitLabel?: string
}>()

const emit = defineEmits<{ submit: [ContactInput], cancel: [] }>()

const { t } = useI18n()

const form = reactive({
  fullName: props.initial?.fullName ?? '',
  role: props.initial?.role ?? '',
  email: props.initial?.email ?? '',
  phone: props.initial?.phone ?? '',
  linkedinUrl: props.initial?.linkedinUrl ?? '',
  preferredLanguage: props.initial?.preferredLanguage ?? '',
})

function onSubmit(): void {
  emit('submit', {
    fullName: form.fullName.trim(),
    role: form.role.trim() || null,
    email: form.email.trim() || null,
    phone: form.phone.trim() || null,
    linkedinUrl: form.linkedinUrl.trim() || null,
    preferredLanguage: form.preferredLanguage.trim().toLowerCase() || null,
  })
}
</script>

<template>
  <form class="flex flex-col gap-3" @submit.prevent="onSubmit">
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <UFormField :label="t('directory.contactForm.fullName')" required>
        <UInput v-model="form.fullName" required class="w-full" />
      </UFormField>
      <UFormField :label="t('directory.contactForm.role')">
        <UInput v-model="form.role" class="w-full" />
      </UFormField>
      <UFormField :label="t('directory.contactForm.email')">
        <UInput v-model="form.email" type="email" class="w-full" />
      </UFormField>
      <UFormField :label="t('directory.contactForm.phone')">
        <UInput v-model="form.phone" class="w-full" />
      </UFormField>
      <UFormField :label="t('directory.contactForm.linkedin')">
        <UInput v-model="form.linkedinUrl" type="url" class="w-full" />
      </UFormField>
      <UFormField :label="t('directory.contactForm.preferredLanguage')">
        <UInput v-model="form.preferredLanguage" maxlength="2" placeholder="fr" class="w-full" />
      </UFormField>
    </div>
    <div class="flex gap-2">
      <UButton type="submit" size="sm" :loading="submitting">{{ submitLabel ?? t('actions.save') }}</UButton>
      <UButton type="button" size="sm" color="neutral" variant="ghost" @click="emit('cancel')">{{ t('actions.cancel') }}</UButton>
    </div>
  </form>
</template>
