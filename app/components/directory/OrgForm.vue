<script setup lang="ts">
import type { OrganizationInput } from '~/types/directory'
import type { OrgFormModel } from '~/utils/organization-form'

const props = defineProps<{
  initial?: OrgFormModel
  submitting?: boolean
  submitLabel?: string
}>()

const emit = defineEmits<{ submit: [OrganizationInput], cancel: [] }>()

const { t } = useI18n()
const { typeOptions, segmentOptions } = useDirectoryLabels()

const form = reactive<OrgFormModel>({
  name: props.initial?.name ?? '',
  type: props.initial?.type ?? 'PUBLISHER',
  website: props.initial?.website ?? '',
  country: props.initial?.country ?? '',
  workingLanguages: props.initial?.workingLanguages ?? '',
  segments: [...(props.initial?.segments ?? [])],
  notes: props.initial?.notes ?? '',
})

function toggleSegment(segment: string, checked: boolean): void {
  form.segments = checked
    ? [...new Set([...form.segments, segment])]
    : form.segments.filter(s => s !== segment)
}

function onSubmit(): void {
  emit('submit', toOrganizationInput(form))
}
</script>

<template>
  <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
    <UFormField :label="t('directory.form.name')" required>
      <UInput v-model="form.name" required class="w-full" />
    </UFormField>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
      <UFormField :label="t('directory.form.type')">
        <USelect v-model="form.type" :items="typeOptions" value-key="value" label-key="label" class="w-full" />
      </UFormField>
      <UFormField :label="t('directory.form.country')">
        <UInput v-model="form.country" maxlength="2" :placeholder="t('directory.form.countryPlaceholder')" class="w-full" />
      </UFormField>
    </div>

    <UFormField :label="t('directory.form.website')">
      <UInput v-model="form.website" type="url" :placeholder="t('directory.form.websitePlaceholder')" class="w-full" />
    </UFormField>

    <UFormField :label="t('directory.form.workingLanguages')" :hint="t('directory.form.workingLanguagesHint')">
      <UInput v-model="form.workingLanguages" placeholder="en fr" class="w-full" />
    </UFormField>

    <UFormField :label="t('directory.form.segments')">
      <div class="flex gap-4 flex-wrap pt-1">
        <UCheckbox
          v-for="option in segmentOptions"
          :key="option.value"
          :label="option.label"
          :model-value="form.segments.includes(option.value)"
          @update:model-value="(checked) => toggleSegment(option.value, checked === true)"
        />
      </div>
    </UFormField>

    <UFormField :label="t('directory.form.notes')">
      <UTextarea v-model="form.notes" :rows="3" class="w-full" />
    </UFormField>

    <div class="flex gap-2 pt-2">
      <UButton type="submit" :loading="submitting">{{ submitLabel ?? t('actions.save') }}</UButton>
      <UButton type="button" color="neutral" variant="ghost" @click="emit('cancel')">{{ t('actions.cancel') }}</UButton>
    </div>
  </form>
</template>
