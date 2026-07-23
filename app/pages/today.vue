<script setup lang="ts">
import type { Lead, Profile, Today } from '~/types/leads'

const { t, locale } = useI18n()
const { pairLabel, priorityLabel } = useLeadLabels()
const todayApi = useToday()
const leadsApi = useLeads()
const profileApi = useProfile()
const toast = useToast()

const queryClient = useQueryClient()

const { data: boardData, isPending: loading } = useQuery({ queryKey: queryKeys.today, queryFn: () => todayApi.get() })
const board = computed<Today | null>(() => boardData.value ?? null)

// Accueil personnalisé (« Bonjour {prénom} ») — le nom vient du profil (page Compte).
const { data: profileData } = useQuery({ queryKey: queryKeys.profile, queryFn: () => profileApi.get() })
const profile = computed<Profile | null>(() => profileData.value ?? null)
const firstName = computed(() => profile.value?.firstName?.trim() || '')

const progress = computed(() => {
  if (!board.value) return 0
  return Math.min(100, Math.round((board.value.weeklyDone / Math.max(1, board.value.weeklyTarget)) * 100))
})

// Actions rapides.
const actingOn = ref<string | null>(null)

async function quickAction(lead: Lead, action: 'contact' | 'follow-up'): Promise<void> {
  actingOn.value = lead.id
  try {
    await leadsApi.transition(lead.id, action)
    await queryClient.invalidateQueries({ queryKey: queryKeys.today })
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
  <PageContainer width="atelier">
    <PageHeader :eyebrow="t('today.eyebrow')" :title="t('today.title')">
      <template v-if="firstName" #subtitle>
        <p class="mt-1 text-muted">{{ t('today.greeting', { name: firstName }) }}</p>
      </template>
    </PageHeader>

    <div v-if="loading" role="status" class="mt-6 flex flex-col gap-6">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <USkeleton class="h-20 rounded-xl" />
      <div class="flex flex-col gap-3">
        <USkeleton class="h-4 w-32 rounded" />
        <USkeleton class="h-20 rounded-xl" />
      </div>
      <div class="flex flex-col gap-3">
        <USkeleton class="h-4 w-32 rounded" />
        <USkeleton class="h-20 rounded-xl" />
      </div>
    </div>

    <template v-else-if="board">
      <!-- Objectif hebdo + série -->
      <section class="mt-6 border border-default rounded-xl p-4 bg-elevated/40">
        <div class="flex items-center gap-3 flex-wrap">
          <p class="text-sm font-semibold">{{ t('today.goal.title') }}</p>
          <span class="font-mono tabular-nums text-sm text-muted">{{ board.weeklyDone }} / {{ board.weeklyTarget }}</span>
          <UBadge v-if="board.streak > 0" color="warning" variant="soft" size="sm">
            <span aria-hidden="true">🔥</span> {{ t('today.goal.streak', { count: board.streak }, board.streak) }}
          </UBadge>
          <UButton
            class="ml-auto"
            size="xs"
            variant="ghost"
            color="neutral"
            icon="i-lucide-pencil"
            :aria-label="t('today.goal.edit')"
            to="/settings"
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
        <ul class="mt-3 border border-default rounded-xl divide-y divide-[var(--ui-border)] rise-stagger">
          <li v-for="lead in board.followUpsDue" :key="lead.id" class="p-4 flex flex-col gap-3 sm:flex-row sm:items-center">
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
            <div class="flex gap-2 flex-wrap shrink-0">
              <UButton
                size="sm"
                variant="outline"
                icon="i-lucide-feather"
                :to="`/leads/${lead.id}?draft=follow-up`"
              >
                {{ t('today.writeFollowUp') }}
              </UButton>
              <UButton
                size="sm"
                icon="i-lucide-send"
                :loading="actingOn === lead.id"
                @click="() => quickAction(lead, 'follow-up')"
              >
                {{ t('pipeline.followUpBlock.done') }}
              </UButton>
            </div>
          </li>
        </ul>
      </section>

      <!-- À contacter -->
      <section v-if="board.toContact.length" class="mt-8">
        <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">
          {{ t('today.toContact') }} <span class="font-mono">{{ board.toContact.length }}</span>
        </p>
        <ul class="mt-3 border border-default rounded-xl divide-y divide-[var(--ui-border)] rise-stagger">
          <li v-for="lead in board.toContact" :key="lead.id" class="p-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <div class="min-w-0 flex-1">
              <NuxtLink :to="`/leads/${lead.id}`" class="font-medium hover:text-primary">
                {{ lead.organizationName }}
              </NuxtLink>
              <div class="text-xs text-dimmed mt-0.5 flex gap-2 items-center">
                <span>{{ priorityLabel(lead.priority) }}</span>
                <LangStamp :code="pairLabel(lead.languagePair)" />
              </div>
            </div>
            <div class="flex gap-2 flex-wrap shrink-0">
              <UButton
                size="sm"
                variant="outline"
                icon="i-lucide-send"
                :loading="actingOn === lead.id"
                @click="() => quickAction(lead, 'contact')"
              >
                {{ t('pipeline.actions.contact') }}
              </UButton>
            </div>
          </li>
        </ul>
      </section>
    </template>
  </PageContainer>
</template>
