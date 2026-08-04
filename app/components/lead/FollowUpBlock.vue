<script setup lang="ts">
import type { Lead } from '~/types/leads'

/**
 * Bloc « Prochaine relance » d'une piste : état de la relance planifiée + (re)planification / annulation.
 * Rendu seulement pour les pistes non terminales/en pause. Émet `changed` après une mutation (le parent
 * rafraîchit le journal ; la piste elle-même est invalidée ici via `invalidateLeadRelated`).
 */
const props = defineProps<{ leadId: string, lead: Lead }>()
const emit = defineEmits<{ changed: [] }>()

const { t, locale } = useI18n()
const leads = useLeads()
const toast = useToast()
const queryClient = useQueryClient()

const canScheduleFollowUp = computed(() => !['WON', 'LOST', 'PAUSED'].includes(props.lead.status))
const followUpOverdue = computed(() => {
  const due = props.lead.nextFollowUpAt
  return Boolean(due && due.slice(0, 10) < new Date().toISOString().slice(0, 10))
})

const scheduling = ref(false)
const scheduleDate = ref('')
const scheduleLabel = ref('')
const savingSchedule = ref(false)

function openScheduler(): void {
  scheduleDate.value = props.lead.nextFollowUpAt?.slice(0, 10) ?? new Date().toISOString().slice(0, 10)
  scheduleLabel.value = props.lead.nextFollowUpLabel ?? ''
  scheduling.value = true
}

async function saveSchedule(): Promise<void> {
  savingSchedule.value = true
  try {
    await leads.scheduleFollowUp(props.leadId, scheduleDate.value, scheduleLabel.value || null)
    scheduling.value = false
    await invalidateLeadRelated(queryClient, props.leadId)
    emit('changed')
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
    await leads.cancelFollowUp(props.leadId)
    await invalidateLeadRelated(queryClient, props.leadId)
    emit('changed')
    toast.add({ title: t('pipeline.toasts.followUpCancelled'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
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
  </section>
</template>
