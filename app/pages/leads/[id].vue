<script setup lang="ts">
import type { Interaction, Lead, LeadAction } from '~/types/leads'

const route = useRoute()
const id = route.params.id as string

const { t, locale } = useI18n()
const { statusLabel, priorityLabel, actionLabel, pairLabel } = useLeadLabels()
const { segmentLabel } = useDirectoryLabels()
const leads = useLeads()
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

const loading = computed(() => status.value === 'idle' || status.value === 'pending')

const transitioning = ref(false)
const confirmLose = ref(false)
const confirmContactWithoutContact = ref(false)
const noteText = ref('')
const savingNote = ref(false)

function onAction(action: LeadAction): void {
  if (action === 'lose') {
    confirmLose.value = true
    return
  }
  // Garde-fou : « Contacter » ne réclame pas de contact (acte manuel), mais sans contact
  // joignable c'est probablement une erreur — on confirme d'abord.
  if (action === 'contact' && lead.value && !lead.value.hasReachableContact) {
    confirmContactWithoutContact.value = true
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
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
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
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    savingNote.value = false
  }
}

/** La projection du journal est asynchrone (worker) : on repasse chercher les retardataires. */
let timelineCatchUpTimer: ReturnType<typeof setTimeout> | null = null
function scheduleTimelineCatchUp(): void {
  if (timelineCatchUpTimer) clearTimeout(timelineCatchUpTimer)
  timelineCatchUpTimer = setTimeout(() => {
    void refreshTimeline()
  }, 1500)
}
onUnmounted(() => {
  if (timelineCatchUpTimer) clearTimeout(timelineCatchUpTimer)
})

/** La section Brouillons a produit de l'activité (génération) : timeline à jour. */
function onDraftActivity(): void {
  void refreshTimeline()
  scheduleTimelineCatchUp()
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
  back_to_contact: 'i-lucide-undo-2',
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
  email_sent: 'i-lucide-mail-check',
  email_send_failed: 'i-lucide-mail-x',
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
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
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
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}

const followUpOverdue = computed(() => {
  const due = lead.value?.nextFollowUpAt
  return Boolean(due && due.slice(0, 10) < new Date().toISOString().slice(0, 10))
})

const canScheduleFollowUp = computed(() =>
  Boolean(lead.value && !['WON', 'LOST', 'PAUSED'].includes(lead.value.status)),
)

</script>

<template>
  <PageContainer width="atelier">
    <UButton variant="link" icon="i-lucide-arrow-left" to="/leads" class="px-0 mb-2">
      {{ t('pipeline.title') }}
    </UButton>

    <div v-if="loading" role="status" class="flex flex-col gap-4">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <USkeleton class="h-9 w-64 rounded" />
      <USkeleton class="h-20 rounded-xl" />
      <USkeleton class="h-40 rounded-xl" />
    </div>
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

      <LeadDraftsSection
        :lead-id="id"
        :language-pair="lead.languagePair"
        :can-generate="canScheduleFollowUp"
        @activity="onDraftActivity"
      />

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
              <p v-else-if="interaction.type === 'reply' && typeof interaction.payload.preview === 'string'" class="text-sm text-muted italic line-clamp-3">
                « {{ interaction.payload.preview }} »
              </p>
              <p v-else-if="interaction.type === 'email_send_failed' && typeof interaction.payload.reason === 'string'" class="text-sm text-error">
                {{ t(`mailbox.failures.${interaction.payload.reason}`, t('mailbox.failures.send_failed')) }}
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

      <ConfirmDialog
        v-model:open="confirmLose"
        :title="t('pipeline.confirmLoseTitle')"
        :description="t('pipeline.confirmLoseBody', { name: lead.organizationName ?? '' })"
        :confirm-label="t('pipeline.actions.lose')"
        danger
        @confirm="() => applyAction('lose')"
      />

      <ConfirmDialog
        v-model:open="confirmContactWithoutContact"
        :title="t('pipeline.confirmContactNoContactTitle')"
        :description="t('pipeline.confirmContactNoContactBody')"
        :confirm-label="t('pipeline.actions.contact')"
        @confirm="() => applyAction('contact')"
      />
    </template>
  </PageContainer>
</template>
