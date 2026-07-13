<script setup lang="ts">
import type { Lead, Today } from '~/types/leads'

const { t, locale } = useI18n()
const { pairLabel, priorityLabel } = useLeadLabels()
const todayApi = useToday()
const leadsApi = useLeads()
const profileApi = useProfile()
const toast = useToast()

const { data: board, refresh, status } = await useAsyncData<Today | null>(
  'today',
  () => todayApi.get(),
  { server: false, default: () => null },
)
const loading = computed(() => status.value === 'idle' || status.value === 'pending')

const progress = computed(() => {
  if (!board.value) return 0
  return Math.min(100, Math.round((board.value.weeklyDone / Math.max(1, board.value.weeklyTarget)) * 100))
})

// Objectif éditable (modale).
const editingGoal = ref(false)
const goalDraft = ref(5)
const savingGoal = ref(false)

function openGoalEditor(): void {
  goalDraft.value = board.value?.weeklyTarget ?? 5
  editingGoal.value = true
}

async function saveGoal(): Promise<void> {
  savingGoal.value = true
  try {
    await profileApi.updateWeeklyGoal(goalDraft.value)
    editingGoal.value = false
    await refresh()
    toast.add({ title: t('directory.toasts.updated'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    savingGoal.value = false
  }
}

// Actions rapides.
const actingOn = ref<string | null>(null)

async function quickAction(lead: Lead, action: 'contact' | 'follow-up'): Promise<void> {
  actingOn.value = lead.id
  try {
    await leadsApi.transition(lead.id, action)
    await refresh()
    toast.add({
      title: action === 'contact' ? t('pipeline.toasts.updated') : t('pipeline.toasts.followUpDone'),
      color: 'success',
    })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    actingOn.value = null
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short' })
}

function isOverdue(lead: Lead): boolean {
  if (!lead.nextFollowUpAt) return false
  return lead.nextFollowUpAt.slice(0, 10) < new Date().toISOString().slice(0, 10)
}
</script>

<template>
  <UContainer class="py-8 max-w-3xl">
    <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">{{ t('today.eyebrow') }}</p>
    <h1 class="font-serif text-3xl font-semibold mt-1">{{ t('today.title') }}</h1>

    <div v-if="loading" class="py-12 text-center text-dimmed">{{ t('common.loading') }}</div>

    <template v-else-if="board">
      <!-- Objectif hebdo + série -->
      <section class="mt-6 border border-default rounded-xl p-4 bg-elevated/40">
        <div class="flex items-center gap-3 flex-wrap">
          <p class="text-sm font-semibold">{{ t('today.goal.title') }}</p>
          <span class="font-mono tabular-nums text-sm text-muted">{{ board.weeklyDone }} / {{ board.weeklyTarget }}</span>
          <UBadge v-if="board.streak > 0" color="warning" variant="soft" size="sm">
            🔥 {{ t('today.goal.streak', { count: board.streak }, board.streak) }}
          </UBadge>
          <UButton
            class="ml-auto"
            size="xs"
            variant="ghost"
            color="neutral"
            icon="i-lucide-pencil"
            :aria-label="t('today.goal.edit')"
            @click="openGoalEditor"
          />
        </div>
        <UProgress :model-value="progress" class="mt-3" :aria-label="t('today.goal.title')" />
      </section>

      <div
        v-if="!board.followUpsDue.length && !board.toContact.length"
        class="mt-6 py-12 text-center text-muted border border-default rounded-xl"
      >
        {{ t('today.empty') }}
      </div>

      <!-- Relances dues -->
      <section v-if="board.followUpsDue.length" class="mt-8">
        <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">
          {{ t('today.followUpsDue') }} <span class="font-mono">{{ board.followUpsDue.length }}</span>
        </p>
        <ul class="mt-3 border border-default rounded-xl divide-y divide-[var(--ui-border)]">
          <li v-for="lead in board.followUpsDue" :key="lead.id" class="p-4 flex items-center gap-3 flex-wrap">
            <div class="min-w-0 flex-1">
              <NuxtLink :to="`/leads/${lead.id}`" class="font-medium hover:text-primary">
                {{ lead.organizationName }}
              </NuxtLink>
              <div class="text-xs mt-0.5 flex gap-2 items-center flex-wrap">
                <span :class="isOverdue(lead) ? 'text-error font-medium' : 'text-dimmed'">
                  {{ lead.nextFollowUpAt
                    ? (isOverdue(lead)
                      ? t('today.overdueSince', { date: formatDate(lead.nextFollowUpAt) })
                      : t('today.dueOn', { date: formatDate(lead.nextFollowUpAt) }))
                    : '' }}
                </span>
                <span v-if="lead.nextFollowUpLabel" class="text-muted">— {{ lead.nextFollowUpLabel }}</span>
                <LangStamp :code="pairLabel(lead.languagePair)" />
              </div>
            </div>
            <UButton
              size="sm"
              icon="i-lucide-send"
              :loading="actingOn === lead.id"
              @click="() => quickAction(lead, 'follow-up')"
            >
              {{ t('pipeline.followUpBlock.done') }}
            </UButton>
          </li>
        </ul>
      </section>

      <!-- À contacter -->
      <section v-if="board.toContact.length" class="mt-8">
        <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">
          {{ t('today.toContact') }} <span class="font-mono">{{ board.toContact.length }}</span>
        </p>
        <ul class="mt-3 border border-default rounded-xl divide-y divide-[var(--ui-border)]">
          <li v-for="lead in board.toContact" :key="lead.id" class="p-4 flex items-center gap-3 flex-wrap">
            <div class="min-w-0 flex-1">
              <NuxtLink :to="`/leads/${lead.id}`" class="font-medium hover:text-primary">
                {{ lead.organizationName }}
              </NuxtLink>
              <div class="text-xs text-dimmed mt-0.5 flex gap-2 items-center">
                <span>{{ priorityLabel(lead.priority) }}</span>
                <LangStamp :code="pairLabel(lead.languagePair)" />
              </div>
            </div>
            <UButton
              size="sm"
              variant="outline"
              icon="i-lucide-send"
              :loading="actingOn === lead.id"
              @click="() => quickAction(lead, 'contact')"
            >
              {{ t('pipeline.actions.contact') }}
            </UButton>
          </li>
        </ul>
      </section>

      <!-- Modale objectif -->
      <UModal v-model:open="editingGoal" :title="t('today.goal.edit')" :description="t('today.goal.hint')">
        <template #body>
          <UFormField :label="t('today.goal.goalLabel')">
            <UInput v-model.number="goalDraft" type="number" min="1" max="99" class="w-full" />
          </UFormField>
        </template>
        <template #footer>
          <div class="flex gap-2 justify-end w-full">
            <UButton color="neutral" variant="ghost" @click="() => { editingGoal = false }">{{ t('actions.cancel') }}</UButton>
            <UButton :loading="savingGoal" @click="saveGoal">{{ t('actions.save') }}</UButton>
          </div>
        </template>
      </UModal>
    </template>
  </UContainer>
</template>
