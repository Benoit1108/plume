<script setup lang="ts">
import type { Contact, ContactInput } from '~/types/directory'

const props = defineProps<{
  initial?: Partial<Contact>
  submitting?: boolean
  submitLabel?: string
}>()

const emit = defineEmits<{ submit: [ContactInput], cancel: [] }>()

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
    <div class="grid grid-cols-2 gap-3">
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">Nom complet</label>
        <UInput v-model="form.fullName" required class="w-full" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">Rôle</label>
        <UInput v-model="form.role" class="w-full" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">Email</label>
        <UInput v-model="form.email" type="email" class="w-full" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">Téléphone</label>
        <UInput v-model="form.phone" class="w-full" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">LinkedIn</label>
        <UInput v-model="form.linkedinUrl" class="w-full" />
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">Langue préférée</label>
        <UInput v-model="form.preferredLanguage" maxlength="2" placeholder="fr" class="w-full" />
      </div>
    </div>
    <div class="flex gap-2">
      <UButton type="submit" size="sm" :loading="submitting">{{ submitLabel ?? 'Enregistrer' }}</UButton>
      <UButton type="button" size="sm" color="neutral" variant="ghost" @click="emit('cancel')">Annuler</UButton>
    </div>
  </form>
</template>
