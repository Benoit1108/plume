<script setup lang="ts">
import type { Draft } from '~/types/drafting'

/**
 * Éditeur de brouillon (relecture humaine, draft-first) : sujet + corps,
 * Copier, Régénérer, Supprimer, Envoyer. Le brouillon ouvert est un v-model
 * (null = fermé) ; la LISTE + le polling restent au parent, qui réaligne le
 * modèle après chaque rafraîchissement. L'éditeur signale `changed` (le parent
 * rafraîchit) et `refresh` (relève manuelle pendant une génération).
 */
const draft = defineModel<Draft | null>('draft', { required: true })

defineProps<{
  /** Boîte connectée : conditionne le bouton Envoyer. */
  canSend: boolean
  /** Adresse de la boîte (récap de confirmation d'envoi). */
  mailboxEmail: string
  /** Le rattrapage a abandonné pendant une génération (bouton Actualiser). */
  pollExhausted: boolean
}>()

const emit = defineEmits<{ changed: [], refresh: [] }>()

const { t } = useI18n()
const draftsApi = useDrafts()
const draftLabels = useDraftLabels()
const toast = useToast()

const editSubject = ref('')
const editBody = ref('')
const savingDraft = ref(false)
const sending = ref(false)
const confirmRegenerate = ref(false)
const confirmDeleteDraft = ref(false)
const confirmSend = ref(false)

// Le parent réaligne `draft` après chaque refresh : on resynchronise les champs.
watch(draft, (value) => {
  if (!value) return
  editSubject.value = value.subject ?? ''
  editBody.value = value.body
}, { immediate: true })

const open = computed({
  get: () => draft.value !== null,
  set: (isOpen: boolean) => {
    if (!isOpen) draft.value = null
  },
})

async function saveDraft(): Promise<void> {
  if (!draft.value) return
  savingDraft.value = true
  try {
    await draftsApi.edit(draft.value.id, { subject: editSubject.value.trim() || null, body: editBody.value })
    emit('changed')
    toast.add({ title: t('drafts.toasts.saved'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    savingDraft.value = false
  }
}

async function copyText(text: string): Promise<void> {
  try {
    await navigator.clipboard.writeText(text)
    toast.add({ title: t('drafts.toasts.copied'), color: 'success' })
  }
  catch {
    toast.add({ title: t('drafts.toasts.copyFailed'), color: 'error' })
  }
}

async function regenerateDraft(): Promise<void> {
  if (!draft.value) return
  try {
    await draftsApi.regenerate(draft.value.id)
    emit('changed')
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}

async function deleteDraft(): Promise<void> {
  if (!draft.value) return
  try {
    await draftsApi.remove(draft.value.id)
    draft.value = null
    emit('changed')
    toast.add({ title: t('drafts.toasts.deleted'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}

async function sendDraft(): Promise<void> {
  if (!draft.value) return
  sending.value = true
  try {
    await draftsApi.send(draft.value.id)
    confirmSend.value = false
    draft.value = null
    emit('changed')
    toast.add({ title: t('drafts.toasts.sendRequested'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    sending.value = false
  }
}
</script>

<template>
  <USlideover v-model:open="open" :title="draft ? draftLabels.typeLabel(draft.type) : ''">
    <template #body>
      <div v-if="draft" class="flex flex-col gap-4">
        <div class="flex items-center gap-2 flex-wrap">
          <UBadge :color="draftLabels.statusColor(draft.status)" variant="soft" size="sm">
            {{ draftLabels.statusLabel(draft.status) }}
          </UBadge>
          <span class="text-xs text-dimmed font-mono uppercase">{{ draft.targetLanguage }}</span>
          <span class="text-xs text-dimmed">{{ t('drafts.draftFirst') }}</span>
        </div>

        <div v-if="draft.status === 'GENERATING'" class="py-12 text-center text-muted text-sm">
          <UIcon name="i-lucide-loader-circle" class="animate-spin text-warning" aria-hidden="true" />
          <p class="mt-2">{{ t('drafts.generating') }}</p>
          <UButton v-if="pollExhausted" class="mt-3" size="xs" variant="soft" icon="i-lucide-refresh-cw" @click="() => emit('refresh')">
            {{ t('drafts.refresh') }}
          </UButton>
        </div>

        <UAlert
          v-else-if="draft.status === 'FAILED'"
          color="error"
          variant="soft"
          icon="i-lucide-alert-triangle"
          :title="draftLabels.failureLabel(draft.failureReason ?? 'generation_failed')"
        />

        <template v-else>
          <UFormField v-if="draft.type !== 'COVER_LETTER' || editSubject" :label="t('drafts.editor.subjectLabel')">
            <div class="flex gap-2">
              <UInput v-model="editSubject" class="flex-1" />
              <UButton
                variant="ghost"
                color="neutral"
                icon="i-lucide-copy"
                :aria-label="t('drafts.editor.copySubject')"
                :disabled="!editSubject"
                @click="() => copyText(editSubject)"
              />
            </div>
          </UFormField>
          <UFormField :label="t('drafts.editor.bodyLabel')">
            <UTextarea v-model="editBody" :rows="14" autoresize class="w-full font-mono text-sm" />
          </UFormField>
        </template>
      </div>
    </template>
    <template #footer>
      <div v-if="draft" class="flex gap-2 w-full flex-wrap">
        <UButton
          color="neutral"
          variant="outline"
          icon="i-lucide-trash-2"
          :aria-label="t('actions.delete')"
          @click="() => { confirmDeleteDraft = true }"
        />
        <UButton
          variant="outline"
          icon="i-lucide-refresh-cw"
          :disabled="draft.status === 'GENERATING'"
          @click="() => { confirmRegenerate = true }"
        >
          {{ t('drafts.editor.regenerate') }}
        </UButton>
        <div class="ml-auto flex gap-2">
          <UButton
            v-if="draft.status === 'READY'"
            variant="soft"
            icon="i-lucide-copy"
            :aria-label="t('drafts.editor.copyBody')"
            @click="() => copyText(editBody)"
          >
            {{ t('drafts.editor.copy') }}
          </UButton>
          <UButton
            v-if="draft.status === 'READY' && canSend"
            icon="i-lucide-send"
            :loading="sending"
            @click="() => { confirmSend = true }"
          >
            {{ t('drafts.editor.send') }}
          </UButton>
          <UButton
            v-if="draft.status === 'READY'"
            :loading="savingDraft"
            :disabled="!editBody.trim()"
            @click="saveDraft"
          >
            {{ t('actions.save') }}
          </UButton>
        </div>
      </div>
    </template>
  </USlideover>

  <ConfirmDialog
    v-model:open="confirmSend"
    :title="t('drafts.editor.confirmSendTitle')"
    :description="t('drafts.editor.confirmSendBody', { mailbox: mailboxEmail })"
    :confirm-label="t('drafts.editor.send')"
    @confirm="sendDraft"
  />
  <ConfirmDialog
    v-model:open="confirmRegenerate"
    :title="t('drafts.editor.confirmRegenerateTitle')"
    :description="t('drafts.editor.confirmRegenerateBody')"
    :confirm-label="t('drafts.editor.regenerate')"
    @confirm="regenerateDraft"
  />
  <ConfirmDialog
    v-model:open="confirmDeleteDraft"
    :title="t('drafts.editor.confirmDeleteTitle')"
    :description="t('drafts.editor.confirmDeleteBody')"
    :confirm-label="t('actions.delete')"
    danger
    @confirm="deleteDraft"
  />
</template>
