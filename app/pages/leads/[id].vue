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
}
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
