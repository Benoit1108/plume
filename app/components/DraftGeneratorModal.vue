<script setup lang="ts">
import type { DraftType, Template } from '~/types/drafting'

/**
 * Modale « Générer un brouillon » : type + langue cible + modèle, puis demande de génération
 * (asynchrone). Ouverte par le parent via `open(type)` (exposé) ; émet `requested` après la demande
 * (le parent rafraîchit la liste + lance le rattrapage du worker).
 */
const props = defineProps<{ leadId: string, languagePair: string }>()
const emit = defineEmits<{ requested: [] }>()

const { t } = useI18n()
const draftsApi = useDrafts()
const draftLabels = useDraftLabels()
const toast = useToast()

const open = ref(false)
const genType = ref<DraftType>('APPLICATION_EMAIL')
const genLanguage = ref('fr')
const genTemplateId = ref('NONE')
const generating = ref(false)
const templates = ref<Template[]>([])

const languageOptions = computed(() => {
  const fromPair = props.languagePair.split('>').map(code => code.trim()).filter(code => code.length === 2)
  return [...new Set([...fromPair.reverse(), 'fr', 'en', 'es'])]
    .map(value => ({ value, label: value.toUpperCase() }))
})

const templateOptions = computed(() => [
  { value: 'NONE', label: t('drafts.modal.noTemplate') },
  ...templates.value
    .filter(template => template.type === genType.value)
    .map(template => ({ value: template.id, label: template.name })),
])

async function openModal(type: DraftType = 'APPLICATION_EMAIL'): Promise<void> {
  genType.value = type
  genLanguage.value = props.languagePair.split('>')[1]?.trim() ?? 'fr'
  genTemplateId.value = 'NONE'
  open.value = true
  try {
    templates.value = await draftsApi.templates()
  }
  catch {
    templates.value = []
  }
}
defineExpose({ open: openModal })

async function submitGenerate(): Promise<void> {
  generating.value = true
  try {
    await draftsApi.generate(props.leadId, {
      type: genType.value,
      targetLanguage: genLanguage.value,
      templateId: genTemplateId.value === 'NONE' ? null : genTemplateId.value,
    })
    open.value = false
    emit('requested')
    toast.add({ title: t('drafts.toasts.requested'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    generating.value = false
  }
}
</script>

<template>
  <UModal v-model:open="open" :title="t('drafts.modal.title')" :description="t('drafts.modal.description')">
    <template #body>
      <div class="flex flex-col gap-4">
        <UFormField :label="t('drafts.modal.typeLabel')" required>
          <USelect v-model="genType" :items="draftLabels.typeOptions.value" class="w-full" />
        </UFormField>
        <UFormField :label="t('drafts.modal.languageLabel')" :hint="t('drafts.modal.languageHint')" required>
          <USelect v-model="genLanguage" :items="languageOptions" class="w-full" />
        </UFormField>
        <UFormField :label="t('drafts.modal.templateLabel')">
          <USelect v-model="genTemplateId" :items="templateOptions" class="w-full" />
        </UFormField>
      </div>
    </template>
    <template #footer>
      <div class="flex gap-2 justify-end w-full">
        <UButton color="neutral" variant="ghost" @click="() => { open = false }">{{ t('actions.cancel') }}</UButton>
        <UButton :loading="generating" icon="i-lucide-feather" @click="submitGenerate">
          {{ t('drafts.modal.submit') }}
        </UButton>
      </div>
    </template>
  </UModal>
</template>
