<script setup lang="ts">
import type { Interaction } from '~/types/leads'

/**
 * Journal d'une piste : ajout de note + liste des interactions. Auto-suffisant (query + rattrapage
 * asynchrone de la projection worker). Le parent déclenche un rafraîchissement via `refresh()`
 * (exposé) après une transition / une activité de brouillon.
 */
const props = defineProps<{ leadId: string }>()

const { t, locale } = useI18n()
const leads = useLeads()
const toast = useToast()
const queryClient = useQueryClient()

const { data: interactionsData } = useQuery({ queryKey: queryKeys.leadTimeline(props.leadId), queryFn: () => leads.timeline(props.leadId) })
const interactions = computed<Interaction[]>(() => interactionsData.value ?? [])

// La projection du journal est asynchrone (worker) : on repasse chercher les retardataires.
const catchUp = useCatchUpRefresh(() => { void queryClient.invalidateQueries({ queryKey: queryKeys.leadTimeline(props.leadId) }) }, { schedule: [1500] })
async function refresh(): Promise<void> {
  await queryClient.invalidateQueries({ queryKey: queryKeys.leadTimeline(props.leadId) })
  catchUp.trigger()
}
defineExpose({ refresh })

const noteText = ref('')
const savingNote = ref(false)
async function submitNote(): Promise<void> {
  if (!noteText.value.trim()) return
  savingNote.value = true
  try {
    await leads.addNote(props.leadId, noteText.value)
    noteText.value = ''
    await refresh()
    toast.add({ title: t('pipeline.toasts.noteAdded'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    savingNote.value = false
  }
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
</script>

<template>
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
</template>
