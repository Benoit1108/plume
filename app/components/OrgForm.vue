<script setup lang="ts">
import type { OrganizationInput, OrganizationType } from '~/types/directory'

const props = defineProps<{
  initial?: {
    name: string
    type: OrganizationType
    website: string
    country: string
    workingLanguages: string
    segments: string[]
    notes: string
  }
  submitting?: boolean
  submitLabel?: string
}>()

const emit = defineEmits<{ submit: [OrganizationInput], cancel: [] }>()

const form = reactive<{
  name: string
  type: OrganizationType
  website: string
  country: string
  workingLanguages: string
  segments: string[]
  notes: string
}>({
  name: props.initial?.name ?? '',
  type: props.initial?.type ?? 'PUBLISHER',
  website: props.initial?.website ?? '',
  country: props.initial?.country ?? '',
  workingLanguages: props.initial?.workingLanguages ?? '',
  segments: [...(props.initial?.segments ?? [])],
  notes: props.initial?.notes ?? '',
})

const segmentOptions = [
  { value: 'PUBLISHING', label: 'Édition' },
  { value: 'AUDIOVISUAL', label: 'Audiovisuel' },
  { value: 'TECHNICAL', label: 'Technique' },
  { value: 'OTHER', label: 'Autre' },
]

const selectClass = 'text-sm rounded-md border border-default bg-default text-default px-3 py-2'

function onSubmit(): void {
  emit('submit', {
    name: form.name.trim(),
    type: form.type,
    website: form.website.trim() || null,
    country: form.country.trim().toUpperCase() || null,
    workingLanguages: form.workingLanguages.split(/[\s,]+/).map(s => s.trim().toLowerCase()).filter(Boolean),
    segments: form.segments,
    notes: form.notes.trim() || null,
  })
}
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <div class="flex flex-col gap-1.5">
      <label class="text-sm font-medium">Nom</label>
      <UInput v-model="form.name" required class="w-full" />
    </div>

    <div class="grid grid-cols-2 gap-4">
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">Type</label>
        <select v-model="form.type" :class="selectClass">
          <option value="PUBLISHER">Éditeur</option>
          <option value="AV_STUDIO">Labo A/V</option>
          <option value="AGENCY">Agence</option>
          <option value="OTHER">Autre</option>
        </select>
      </div>
      <div class="flex flex-col gap-1.5">
        <label class="text-sm font-medium">Pays</label>
        <UInput v-model="form.country" maxlength="2" placeholder="FR" class="w-full" />
      </div>
    </div>

    <div class="flex flex-col gap-1.5">
      <label class="text-sm font-medium">Site web</label>
      <UInput v-model="form.website" placeholder="https://…" class="w-full" />
    </div>

    <div class="flex flex-col gap-1.5">
      <label class="text-sm font-medium">
        Langues de travail <span class="text-dimmed font-normal">(codes séparés par un espace, ex. en fr)</span>
      </label>
      <UInput v-model="form.workingLanguages" placeholder="en fr" class="w-full" />
    </div>

    <div class="flex flex-col gap-1.5">
      <span class="text-sm font-medium">Segments</span>
      <div class="flex gap-4 flex-wrap">
        <label v-for="s in segmentOptions" :key="s.value" class="flex items-center gap-2 text-sm">
          <input v-model="form.segments" type="checkbox" :value="s.value"> {{ s.label }}
        </label>
      </div>
    </div>

    <div class="flex flex-col gap-1.5">
      <label class="text-sm font-medium">Notes</label>
      <UTextarea v-model="form.notes" :rows="3" class="w-full" />
    </div>

    <div class="flex gap-2 pt-2">
      <UButton type="submit" :loading="submitting">{{ submitLabel ?? 'Enregistrer' }}</UButton>
      <UButton type="button" color="neutral" variant="ghost" @click="emit('cancel')">Annuler</UButton>
    </div>
  </form>
</template>
