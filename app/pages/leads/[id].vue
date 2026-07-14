<script setup lang="ts">
import type { Interaction, Lead, LeadAction } from '~/types/leads'
import type { Draft, DraftType, Template } from '~/types/drafting'

const route = useRoute()
const id = route.params.id as string

const { t, locale } = useI18n()
const { statusLabel, priorityLabel, actionLabel, pairLabel } = useLeadLabels()
const { segmentLabel } = useDirectoryLabels()
const leads = useLeads()
const draftsApi = useDrafts()
const draftLabels = useDraftLabels()
const toast = useToast()

const { data: lead, refresh, status } = await useAsyncData<Lead | null>(
  `lead-${id}`,
  () => leads.get(id),
  { server: false, default: () => null },
)
const { data: interactions, refresh: refreshTimeline } = await useAsyncData<Interaction[]>(
  `lead-${id}-timeline`,
  () => leads.timeline(id),
  { server: false, default: () => [] },
)
const { data: leadDrafts, refresh: refreshDrafts } = await useAsyncData<Draft[]>(
  `lead-${id}-drafts`,
  () => draftsApi.forLead(id),
  { server: false, default: () => [] },
)

const loading = computed(() => status.value === 'idle' || status.value === 'pending')

const transitioning = ref(false)
const confirmLose = ref(false)
const noteText = ref('')
const savingNote = ref(false)

function onAction(action: LeadAction): void {
  if (action === 'lose') {
    confirmLose.value = true
    return
  }
  void applyAction(action)
}

async function applyAction(action: LeadAction): Promise<void> {
  transitioning.value = true
  try {
    await leads.transition(id, action)
    await Promise.all([refresh(), refreshTimeline()])
    scheduleTimelineCatchUp()
    toast.add({ title: t('pipeline.toasts.updated'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    transitioning.value = false
  }
}

async function submitNote(): Promise<void> {
  if (!noteText.value.trim()) return
  savingNote.value = true
  try {
    await leads.addNote(id, noteText.value)
    noteText.value = ''
    await refreshTimeline()
    scheduleTimelineCatchUp()
    toast.add({ title: t('pipeline.toasts.noteAdded'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    savingNote.value = false
  }
}

/** La projection du journal est asynchrone (worker) : on repasse chercher les retardataires. */
function scheduleTimelineCatchUp(): void {
  setTimeout(() => {
    void refreshTimeline()
  }, 1500)
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' })
}

function timelineLabel(interaction: Interaction): string {
  return t(`pipeline.timeline.${interaction.type}`, interaction.type)
}

const timelineIcon: Record<Interaction['type'], string> = {
  created: 'i-lucide-sparkles',
  contacted: 'i-lucide-send',
  reply: 'i-lucide-mail-open',
  sample_test: 'i-lucide-flask-conical',
  won: 'i-lucide-trophy',
  lost: 'i-lucide-x-circle',
  paused: 'i-lucide-pause',
  resumed: 'i-lucide-play',
  note: 'i-lucide-sticky-note',
  follow_up_scheduled: 'i-lucide-alarm-clock',
  followed_up: 'i-lucide-alarm-clock-check',
  follow_up_cancelled: 'i-lucide-alarm-clock-off',
  draft_generated: 'i-lucide-feather',
}

// ----- Relance (bloc « Prochaine relance ») -----
const scheduling = ref(false)
const scheduleDate = ref('')
const scheduleLabel = ref('')
const savingSchedule = ref(false)

function openScheduler(): void {
  scheduleDate.value = lead.value?.nextFollowUpAt?.slice(0, 10) ?? new Date().toISOString().slice(0, 10)
  scheduleLabel.value = lead.value?.nextFollowUpLabel ?? ''
  scheduling.value = true
}

async function saveSchedule(): Promise<void> {
  savingSchedule.value = true
  try {
    await leads.scheduleFollowUp(id, scheduleDate.value, scheduleLabel.value || null)
    scheduling.value = false
    await Promise.all([refresh(), refreshTimeline()])
    scheduleTimelineCatchUp()
    toast.add({ title: t('pipeline.toasts.followUpScheduled'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    savingSchedule.value = false
  }
}

async function cancelSchedule(): Promise<void> {
  try {
    await leads.cancelFollowUp(id)
    await Promise.all([refresh(), refreshTimeline()])
    scheduleTimelineCatchUp()
    toast.add({ title: t('pipeline.toasts.followUpCancelled'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
}

const followUpOverdue = computed(() => {
  const due = lead.value?.nextFollowUpAt
  return Boolean(due && due.slice(0, 10) < new Date().toISOString().slice(0, 10))
})

const canScheduleFollowUp = computed(() =>
  Boolean(lead.value && !['WON', 'LOST', 'PAUSED'].includes(lead.value.status)),
)

// ----- Brouillons (rédaction assistée, draft-first) -----
const generateOpen = ref(false)
const genType = ref<DraftType>('APPLICATION_EMAIL')
const genLanguage = ref('fr')
const genTemplateId = ref('NONE')
const generating = ref(false)
const templates = ref<Template[]>([])

/** Langue par défaut du message = la langue du prospect (côté cible de la paire). */
const languageOptions = computed(() => {
  const pair = lead.value?.languagePair ?? ''
  const fromPair = pair.split('>').map(code => code.trim()).filter(code => code.length === 2)
  return [...new Set([...fromPair.reverse(), 'fr', 'en', 'es'])]
    .map(value => ({ value, label: value.toUpperCase() }))
})

const templateOptions = computed(() => [
  { value: 'NONE', label: t('drafts.modal.noTemplate') },
  ...templates.value
    .filter(template => template.type === genType.value)
    .map(template => ({ value: template.id, label: template.name })),
])

async function openGenerator(): Promise<void> {
  genType.value = 'APPLICATION_EMAIL'
  genLanguage.value = lead.value?.languagePair.split('>')[1]?.trim() ?? 'fr'
  genTemplateId.value = 'NONE'
  generateOpen.value = true
  try {
    templates.value = await draftsApi.templates()
  }
  catch {
    templates.value = []
  }
}

async function submitGenerate(): Promise<void> {
  generating.value = true
  try {
    await draftsApi.generate(id, {
      type: genType.value,
      targetLanguage: genLanguage.value,
      templateId: genTemplateId.value === 'NONE' ? null : genTemplateId.value,
    })
    generateOpen.value = false
    await refreshDrafts()
    scheduleDraftCatchUp()
    scheduleTimelineCatchUp()
    toast.add({ title: t('drafts.toasts.requested'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    generating.value = false
  }
}

/** La génération est asynchrone (worker) : on repasse tant qu'un brouillon est en cours. */
let draftPollTimer: ReturnType<typeof setTimeout> | null = null
function scheduleDraftCatchUp(attempt = 0): void {
  if (attempt >= 8) return
  if (draftPollTimer) clearTimeout(draftPollTimer)
  draftPollTimer = setTimeout(() => {
    void refreshDrafts().then(() => {
      syncEditorWithList()
      if (leadDrafts.value.some(draft => draft.status === 'GENERATING')) {
        scheduleDraftCatchUp(attempt + 1)
      }
      else {
        void refreshTimeline()
      }
    })
  }, 1500)
}
onUnmounted(() => {
  if (draftPollTimer) clearTimeout(draftPollTimer)
})

// Éditeur (relecture humaine : sujet + corps, Copier, Régénérer, Supprimer).
const editingDraft = ref<Draft | null>(null)
const editSubject = ref('')
const editBody = ref('')
const savingDraft = ref(false)
const confirmRegenerate = ref(false)
const confirmDeleteDraft = ref(false)

function openDraft(draft: Draft): void {
  editingDraft.value = draft
  editSubject.value = draft.subject ?? ''
  editBody.value = draft.body
  if (draft.status === 'GENERATING') scheduleDraftCatchUp()
}

/** Après un refresh, réaligne l'éditeur ouvert sur l'état serveur (READY/FAILED). */
function syncEditorWithList(): void {
  if (!editingDraft.value) return
  const fresh = leadDrafts.value.find(draft => draft.id === editingDraft.value?.id)
  if (fresh && fresh.updatedAt !== editingDraft.value.updatedAt) {
    openDraft(fresh)
  }
  else if (fresh) {
    editingDraft.value = fresh
  }
}

async function saveDraft(): Promise<void> {
  if (!editingDraft.value) return
  savingDraft.value = true
  try {
    await draftsApi.edit(editingDraft.value.id, { subject: editSubject.value.trim() || null, body: editBody.value })
    await refreshDrafts()
    syncEditorWithList()
    toast.add({ title: t('drafts.toasts.saved'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
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
  if (!editingDraft.value) return
  try {
    await draftsApi.regenerate(editingDraft.value.id)
    await refreshDrafts()
    syncEditorWithList()
    scheduleDraftCatchUp()
    scheduleTimelineCatchUp()
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
}

async function deleteDraft(): Promise<void> {
  if (!editingDraft.value) return
  try {
    await draftsApi.remove(editingDraft.value.id)
    editingDraft.value = null
    await refreshDrafts()
    toast.add({ title: t('drafts.toasts.deleted'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
}

const editorOpen = computed({
  get: () => editingDraft.value !== null,
  set: (open: boolean) => {
    if (!open) editingDraft.value = null
  },
})
</script>

<template>
  <UContainer class="py-8 max-w-3xl">
    <UButton variant="link" icon="i-lucide-arrow-left" to="/leads" class="px-0 mb-2">
      {{ t('pipeline.title') }}
    </UButton>

    <div v-if="loading" class="text-dimmed py-12">{{ t('common.loading') }}</div>
    <div v-else-if="!lead" class="text-muted py-12">{{ t('pipeline.detail.notFound') }}</div>

    <template v-else>
      <div class="flex flex-col sm:flex-row sm:items-start gap-4">
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="font-serif text-3xl font-semibold">{{ lead.organizationName }}</h1>
            <UBadge variant="soft">{{ statusLabel(lead.status) }}</UBadge>
            <UBadge color="neutral" variant="outline" size="sm">{{ priorityLabel(lead.priority) }}</UBadge>
          </div>
          <div class="mt-2 flex gap-2 items-center flex-wrap text-sm text-muted">
            <LangStamp :code="pairLabel(lead.languagePair)" />
            <UBadge color="neutral" variant="soft" size="sm">{{ segmentLabel(lead.segment) }}</UBadge>
            <NuxtLink :to="`/organizations/${lead.organizationId}`" class="underline underline-offset-2 hover:text-primary text-xs">
              {{ t('nav.directory') }} →
            </NuxtLink>
          </div>
          <div class="mt-2 text-xs text-dimmed flex gap-3 flex-wrap">
            <span>{{ t('pipeline.detail.createdAt', { date: formatDate(lead.createdAt) }) }}</span>
            <span v-if="lead.lastContactedAt">{{ t('pipeline.detail.lastContact', { date: formatDate(lead.lastContactedAt) }) }}</span>
            <span v-if="lead.lastReplyAt">{{ t('pipeline.detail.lastReply', { date: formatDate(lead.lastReplyAt) }) }}</span>
          </div>
        </div>

        <!-- Seules les transitions légales sont proposées (allowedActions du read model). -->
        <div class="flex gap-2 shrink-0 flex-wrap sm:ml-auto">
          <UButton
            v-for="action in lead.allowedActions"
            :key="action"
            size="sm"
            :color="action === 'lose' ? 'error' : action === 'win' ? 'success' : 'primary'"
            :variant="['lose', 'pause'].includes(action) ? 'outline' : 'solid'"
            :loading="transitioning"
            @click="() => onAction(action)"
          >
            {{ actionLabel(action) }}
          </UButton>
        </div>
      </div>

      <!-- Prochaine relance -->
      <section v-if="canScheduleFollowUp" class="mt-8 border border-default rounded-xl p-4 bg-elevated/40">
        <div class="flex items-center gap-3 flex-wrap">
          <UIcon name="i-lucide-alarm-clock" class="text-primary shrink-0" aria-hidden="true" />
          <p class="text-sm font-semibold">{{ t('pipeline.followUpBlock.title') }}</p>
          <template v-if="lead.nextFollowUpAt">
            <span class="text-sm" :class="followUpOverdue ? 'text-error font-medium' : 'text-muted'">
              {{ followUpOverdue
                ? t('pipeline.followUpBlock.overdue', { date: formatDate(lead.nextFollowUpAt) })
                : t('pipeline.followUpBlock.dueOn', { date: formatDate(lead.nextFollowUpAt) }) }}
            </span>
            <span v-if="lead.nextFollowUpLabel" class="text-sm text-dimmed">— {{ lead.nextFollowUpLabel }}</span>
          </template>
          <span v-else class="text-sm text-dimmed">{{ t('pipeline.followUpBlock.none') }}</span>
          <div class="ml-auto flex gap-2">
            <UButton size="xs" variant="outline" icon="i-lucide-calendar" @click="openScheduler">
              {{ t('pipeline.followUpBlock.reschedule') }}
            </UButton>
            <UButton
              v-if="lead.nextFollowUpAt"
              size="xs"
              variant="ghost"
              color="neutral"
              icon="i-lucide-alarm-clock-off"
              @click="cancelSchedule"
            >
              {{ t('pipeline.followUpBlock.cancel') }}
            </UButton>
          </div>
        </div>
      </section>

      <!-- Brouillons (rédaction assistée) -->
      <section class="mt-10">
        <div class="flex items-center gap-3 flex-wrap">
          <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">{{ t('drafts.title') }}</p>
          <UButton
            v-if="canScheduleFollowUp"
            class="ml-auto"
            size="xs"
            variant="outline"
            icon="i-lucide-feather"
            @click="openGenerator"
          >
            {{ t('drafts.generate') }}
          </UButton>
        </div>

        <ul v-if="leadDrafts.length" class="mt-3 border border-default rounded-xl divide-y divide-[var(--ui-border)]">
          <li v-for="draft in leadDrafts" :key="draft.id">
            <button
              type="button"
              class="w-full p-3 flex items-center gap-3 text-left hover:bg-elevated/60 rounded-xl"
              @click="() => openDraft(draft)"
            >
              <UIcon
                :name="draft.status === 'GENERATING' ? 'i-lucide-loader-circle' : 'i-lucide-feather'"
                :class="['shrink-0', draft.status === 'GENERATING' ? 'animate-spin text-warning' : 'text-primary']"
                aria-hidden="true"
              />
              <div class="min-w-0 flex-1">
                <div class="text-sm font-medium flex items-center gap-2 flex-wrap">
                  {{ draftLabels.typeLabel(draft.type) }}
                  <span class="text-xs text-dimmed font-mono uppercase">{{ draft.targetLanguage }}</span>
                  <UBadge :color="draftLabels.statusColor(draft.status)" variant="soft" size="sm">
                    {{ draftLabels.statusLabel(draft.status) }}
                  </UBadge>
                </div>
                <p v-if="draft.status === 'READY' && draft.subject" class="text-xs text-muted truncate mt-0.5">{{ draft.subject }}</p>
                <p v-else-if="draft.status === 'FAILED' && draft.failureReason" class="text-xs text-error mt-0.5">
                  {{ draftLabels.failureLabel(draft.failureReason) }}
                </p>
              </div>
              <time class="text-xs text-dimmed shrink-0 tabular-nums" :datetime="draft.updatedAt">
                {{ formatDate(draft.updatedAt) }}
              </time>
            </button>
          </li>
        </ul>
        <p v-else class="mt-3 p-4 text-sm text-muted border border-default rounded-xl">
          {{ t('drafts.empty') }}
        </p>
      </section>

      <section class="mt-10">
        <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">{{ t('pipeline.detail.timeline') }}</p>

        <form class="mt-3 flex gap-2" @submit.prevent="submitNote">
          <UInput
            v-model="noteText"
            :placeholder="t('pipeline.detail.notePlaceholder')"
            :aria-label="t('pipeline.detail.addNote')"
            class="flex-1"
          />
          <UButton type="submit" size="sm" variant="outline" icon="i-lucide-sticky-note" :loading="savingNote" :disabled="!noteText.trim()">
            {{ t('pipeline.detail.addNote') }}
          </UButton>
        </form>

        <ol class="mt-4 border border-default rounded-lg divide-y divide-[var(--ui-border)]">
          <li v-for="interaction in interactions" :key="interaction.id" class="p-3 flex gap-3 items-start">
            <UIcon :name="timelineIcon[interaction.type] ?? 'i-lucide-circle'" class="mt-0.5 text-primary shrink-0" aria-hidden="true" />
            <div class="min-w-0">
              <div class="text-sm font-medium">{{ timelineLabel(interaction) }}</div>
              <p v-if="interaction.type === 'note' && typeof interaction.payload.text === 'string'" class="text-sm text-muted whitespace-pre-line">
                {{ interaction.payload.text }}
              </p>
            </div>
            <time class="ml-auto text-xs text-dimmed shrink-0 tabular-nums" :datetime="interaction.occurredOn">
              {{ formatDate(interaction.occurredOn) }}
            </time>
          </li>
          <li v-if="!interactions.length" class="p-6 text-center text-muted text-sm">
            {{ t('pipeline.detail.noInteractions') }}
          </li>
        </ol>
      </section>

      <!-- Modale de (re)planification -->
      <UModal v-model:open="scheduling" :title="t('pipeline.followUpBlock.scheduleTitle')">
        <template #body>
          <div class="flex flex-col gap-4">
            <UFormField :label="t('pipeline.followUpBlock.dateLabel')" required>
              <UInput v-model="scheduleDate" type="date" class="w-full" />
            </UFormField>
            <UFormField :label="t('pipeline.followUpBlock.labelLabel')">
              <UInput v-model="scheduleLabel" class="w-full" />
            </UFormField>
          </div>
        </template>
        <template #footer>
          <div class="flex gap-2 justify-end w-full">
            <UButton color="neutral" variant="ghost" @click="() => { scheduling = false }">{{ t('actions.cancel') }}</UButton>
            <UButton :loading="savingSchedule" :disabled="!scheduleDate" @click="saveSchedule">
              {{ t('pipeline.followUpBlock.schedule') }}
            </UButton>
          </div>
        </template>
      </UModal>

      <!-- Modale « Générer un brouillon » -->
      <UModal v-model:open="generateOpen" :title="t('drafts.modal.title')" :description="t('drafts.modal.description')">
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
            <UButton color="neutral" variant="ghost" @click="() => { generateOpen = false }">{{ t('actions.cancel') }}</UButton>
            <UButton :loading="generating" icon="i-lucide-feather" @click="submitGenerate">
              {{ t('drafts.modal.submit') }}
            </UButton>
          </div>
        </template>
      </UModal>

      <!-- Éditeur de brouillon (relecture humaine) -->
      <USlideover v-model:open="editorOpen" :title="editingDraft ? draftLabels.typeLabel(editingDraft.type) : ''">
        <template #body>
          <div v-if="editingDraft" class="flex flex-col gap-4">
            <div class="flex items-center gap-2 flex-wrap">
              <UBadge :color="draftLabels.statusColor(editingDraft.status)" variant="soft" size="sm">
                {{ draftLabels.statusLabel(editingDraft.status) }}
              </UBadge>
              <span class="text-xs text-dimmed font-mono uppercase">{{ editingDraft.targetLanguage }}</span>
              <span class="text-xs text-dimmed">{{ t('drafts.draftFirst') }}</span>
            </div>

            <div v-if="editingDraft.status === 'GENERATING'" class="py-12 text-center text-muted text-sm">
              <UIcon name="i-lucide-loader-circle" class="animate-spin text-warning" aria-hidden="true" />
              <p class="mt-2">{{ t('drafts.generating') }}</p>
            </div>

            <UAlert
              v-else-if="editingDraft.status === 'FAILED'"
              color="error"
              variant="soft"
              icon="i-lucide-alert-triangle"
              :title="draftLabels.failureLabel(editingDraft.failureReason ?? 'generation_failed')"
            />

            <template v-else>
              <UFormField v-if="editingDraft.type !== 'COVER_LETTER' || editSubject" :label="t('drafts.editor.subjectLabel')">
                <div class="flex gap-2">
                  <UInput v-model="editSubject" class="flex-1" />
                  <UButton
                    variant="ghost"
                    color="neutral"
                    icon="i-lucide-copy"
                    :aria-label="t('drafts.editor.copy')"
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
          <div v-if="editingDraft" class="flex gap-2 w-full flex-wrap">
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
              :disabled="editingDraft.status === 'GENERATING'"
              @click="() => { confirmRegenerate = true }"
            >
              {{ t('drafts.editor.regenerate') }}
            </UButton>
            <div class="ml-auto flex gap-2">
              <UButton
                v-if="editingDraft.status === 'READY'"
                variant="soft"
                icon="i-lucide-copy"
                @click="() => copyText(editBody)"
              >
                {{ t('drafts.editor.copy') }}
              </UButton>
              <UButton
                v-if="editingDraft.status === 'READY'"
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

      <ConfirmDialog
        v-model:open="confirmLose"
        :title="t('pipeline.confirmLoseTitle')"
        :description="t('pipeline.confirmLoseBody', { name: lead.organizationName ?? '' })"
        :confirm-label="t('pipeline.actions.lose')"
        danger
        @confirm="() => applyAction('lose')"
      />
    </template>
  </UContainer>
</template>
