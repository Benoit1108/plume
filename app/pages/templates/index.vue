<script setup lang="ts">
import type { Template, TemplateInput } from '~/types/drafting'

type TemplateForm = Omit<TemplateInput, 'subject'> & { subject: string }

const { t } = useI18n()
const drafts = useDrafts()
const draftLabels = useDraftLabels()
const { segmentLabel, segmentOptions } = useDirectoryLabels()
const toast = useToast()

const { data: templates, refresh, status } = await useAsyncData<Template[]>(
  'templates',
  () => drafts.templates(),
  { server: false, default: () => [] },
)
const loading = computed(() => status.value === 'idle' || status.value === 'pending')

const VARIABLES = '{{contact}}, {{organisation}}, {{langues}}, {{bio}}, {{specialites}}, {{signature}}'

// ----- Formulaire (création / édition, slideover) -----
const formOpen = ref(false)
const editingId = ref<string | null>(null)
const form = ref<TemplateForm>(emptyForm())
const saving = ref(false)

function emptyForm(): TemplateForm {
  return { name: '', type: 'APPLICATION_EMAIL', segment: 'PUBLISHING', language: 'fr', subject: '', body: '' }
}

function openCreate(): void {
  editingId.value = null
  form.value = emptyForm()
  formOpen.value = true
}

function openEdit(template: Template): void {
  editingId.value = template.id
  form.value = {
    name: template.name,
    type: template.type,
    segment: template.segment,
    language: template.language,
    subject: template.subject ?? '',
    body: template.body,
  }
  formOpen.value = true
}

const languageOptions = [
  { value: 'fr', label: 'FR' },
  { value: 'en', label: 'EN' },
  { value: 'es', label: 'ES' },
]

const formValid = computed(() => form.value.name.trim() !== '' && form.value.body.trim() !== '')

async function submit(): Promise<void> {
  saving.value = true
  const payload: TemplateInput = { ...form.value, subject: form.value.subject.trim() || null }
  try {
    if (editingId.value) {
      await drafts.updateTemplate(editingId.value, payload)
      toast.add({ title: t('templates.toasts.updated'), color: 'success' })
    }
    else {
      await drafts.createTemplate(payload)
      toast.add({ title: t('templates.toasts.created'), color: 'success' })
    }
    formOpen.value = false
    await refresh()
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    saving.value = false
  }
}

// ----- Suppression (confirmation) -----
const deleting = ref<Template | null>(null)
const confirmDelete = computed({
  get: () => deleting.value !== null,
  set: (open: boolean) => {
    if (!open) deleting.value = null
  },
})

async function removeTemplate(): Promise<void> {
  if (!deleting.value) return
  try {
    await drafts.removeTemplate(deleting.value.id)
    deleting.value = null
    await refresh()
    toast.add({ title: t('templates.toasts.deleted'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
}
</script>

<template>
  <UContainer class="py-8 max-w-3xl">
    <div class="flex items-start gap-3 flex-wrap">
      <div class="min-w-0">
        <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">{{ t('templates.eyebrow') }}</p>
        <h1 class="font-serif text-3xl font-semibold mt-1">{{ t('templates.title') }}</h1>
        <p class="mt-2 text-sm text-muted">
          {{ t('templates.intro') }}
          <code class="text-xs bg-elevated rounded px-1 py-0.5 break-all">{{ VARIABLES }}</code>
        </p>
      </div>
      <UButton class="ml-auto shrink-0" icon="i-lucide-plus" @click="openCreate">
        {{ t('templates.new') }}
      </UButton>
    </div>

    <div v-if="loading" class="py-12 text-center text-dimmed">{{ t('common.loading') }}</div>

    <ul v-else-if="templates.length" class="mt-6 border border-default rounded-xl divide-y divide-[var(--ui-border)]">
      <li v-for="template in templates" :key="template.id" class="p-4 flex items-center gap-3 flex-wrap">
        <div class="min-w-0 flex-1">
          <button type="button" class="font-medium hover:text-primary text-left" @click="() => openEdit(template)">
            {{ template.name }}
          </button>
          <div class="text-xs text-dimmed mt-0.5 flex gap-2 items-center flex-wrap">
            <span>{{ draftLabels.typeLabel(template.type) }}</span>
            <UBadge color="neutral" variant="soft" size="sm">{{ segmentLabel(template.segment) }}</UBadge>
            <span class="font-mono uppercase">{{ template.language }}</span>
          </div>
        </div>
        <UButton
          size="xs"
          variant="ghost"
          color="neutral"
          icon="i-lucide-pencil"
          :aria-label="t('actions.edit')"
          @click="() => openEdit(template)"
        />
        <UButton
          size="xs"
          variant="ghost"
          color="error"
          icon="i-lucide-trash-2"
          :aria-label="t('actions.delete')"
          @click="() => { deleting = template }"
        />
      </li>
    </ul>
    <p v-else class="mt-6 p-6 text-center text-muted border border-default rounded-xl">
      {{ t('templates.empty') }}
    </p>

    <!-- Formulaire création / édition -->
    <USlideover v-model:open="formOpen" :title="editingId ? t('templates.form.editTitle') : t('templates.form.createTitle')">
      <template #body>
        <div class="flex flex-col gap-4">
          <UFormField :label="t('templates.form.nameLabel')" required>
            <UInput v-model="form.name" class="w-full" />
          </UFormField>
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <UFormField :label="t('templates.form.typeLabel')" required>
              <USelect v-model="form.type" :items="draftLabels.typeOptions.value" class="w-full" />
            </UFormField>
            <UFormField :label="t('templates.form.segmentLabel')" required>
              <USelect v-model="form.segment" :items="segmentOptions" class="w-full" />
            </UFormField>
            <UFormField :label="t('templates.form.languageLabel')" required>
              <USelect v-model="form.language" :items="languageOptions" class="w-full" />
            </UFormField>
          </div>
          <UFormField :label="t('templates.form.subjectLabel')">
            <UInput v-model="form.subject" class="w-full" />
          </UFormField>
          <UFormField :label="t('templates.form.bodyLabel')" required>
            <UTextarea v-model="form.body" :rows="12" autoresize class="w-full font-mono text-sm" />
          </UFormField>
          <p class="text-xs text-dimmed">
            <code class="bg-elevated rounded px-1 py-0.5 break-all">{{ VARIABLES }}</code>
          </p>
        </div>
      </template>
      <template #footer>
        <div class="flex gap-2 justify-end w-full">
          <UButton color="neutral" variant="ghost" @click="() => { formOpen = false }">{{ t('actions.cancel') }}</UButton>
          <UButton :loading="saving" :disabled="!formValid" @click="submit">{{ t('actions.save') }}</UButton>
        </div>
      </template>
    </USlideover>

    <ConfirmDialog
      v-model:open="confirmDelete"
      :title="t('templates.confirmDeleteTitle', { name: deleting?.name ?? '' })"
      :description="t('templates.confirmDeleteBody')"
      :confirm-label="t('actions.delete')"
      danger
      @confirm="removeTemplate"
    />
  </UContainer>
</template>
