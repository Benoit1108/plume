<script setup lang="ts">
import type { Lead, LeadAction } from '~/types/leads'

const route = useRoute()
const id = route.params.id as string

const { t, locale } = useI18n()
const { statusLabel, priorityLabel, actionLabel, pairLabel } = useLeadLabels()
const { segmentLabel } = useDirectoryLabels()
const leads = useLeads()
const toast = useToast()

const queryClient = useQueryClient()
const { data: leadData, isPending: loading, isError, refetch } = useQuery({ queryKey: queryKeys.lead(id), queryFn: () => leads.get(id) })
const lead = computed<Lead | null>(() => leadData.value ?? null)

// Une transition impacte la fiche, le kanban, l'écran du jour ET les KPI du tableau de bord.
async function refresh(): Promise<void> {
  await invalidateLeadRelated(queryClient, id)
}
// Le journal (LeadTimeline) gère sa propre query + rattrapage : on lui demande de se rafraîchir.
const timeline = ref<{ refresh: () => void } | null>(null)
function refreshTimeline(): void {
  timeline.value?.refresh()
}

// Génération de brouillons possible tant que la piste n'est pas terminale/en pause.
const canGenerate = computed(() => Boolean(lead.value && !['WON', 'LOST', 'PAUSED'].includes(lead.value.status)))

// Valeur estimée du deal (euros) — saisie inline.
const estimatedValueInput = ref<number | null>(null)
watch(() => lead.value?.estimatedValue, (v) => { estimatedValueInput.value = v ?? null }, { immediate: true })
const savingValue = ref(false)
async function saveEstimatedValue(): Promise<void> {
  if (savingValue.value) return
  savingValue.value = true
  try {
    const v = estimatedValueInput.value
    await leads.setEstimatedValue(id, typeof v === 'number' && v >= 1 ? Math.round(v) : null)
    await refresh()
    toast.add({ title: t('pipeline.estimatedValue.saved'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    savingValue.value = false
  }
}

const transitioning = ref(false)
const confirmLose = ref(false)
const confirmContactWithoutContact = ref(false)

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
    await refresh()
    refreshTimeline()
    toast.add({ title: t('pipeline.toasts.updated'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    transitioning.value = false
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' })
}
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
    <UAlert
      v-else-if="isError"
      color="error"
      variant="subtle"
      :title="t('common.loadError')"
      class="my-6"
    >
      <template #actions>
        <UButton size="xs" color="error" variant="outline" icon="i-lucide-refresh-cw" @click="() => { void refetch() }">
          {{ t('common.retry') }}
        </UButton>
      </template>
    </UAlert>
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

      <!-- Valeur estimée du deal -->
      <section class="mt-8 border border-default rounded-xl p-4 bg-elevated/40">
        <div class="flex items-center gap-3 flex-wrap">
          <UIcon name="i-lucide-euro" class="text-primary shrink-0" aria-hidden="true" />
          <div class="min-w-0 flex-1">
            <p class="text-sm font-semibold">{{ t('pipeline.estimatedValue.title') }}</p>
            <p class="text-xs text-muted">{{ t('pipeline.estimatedValue.hint') }}</p>
          </div>
          <UInput
            v-model.number="estimatedValueInput"
            type="number"
            min="1"
            max="100000000"
            class="w-40"
            :placeholder="t('pipeline.estimatedValue.placeholder')"
            :aria-label="t('pipeline.estimatedValue.title')"
          />
          <UButton size="sm" variant="soft" :loading="savingValue" @click="saveEstimatedValue">{{ t('actions.save') }}</UButton>
        </div>
      </section>

      <FollowUpBlock :lead-id="id" :lead="lead" @changed="refreshTimeline" />

      <LeadDraftsSection
        :lead-id="id"
        :language-pair="lead.languagePair"
        :can-generate="canGenerate"
        @activity="refreshTimeline"
      />

      <LeadTimeline ref="timeline" :lead-id="id" />

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
        :confirm-label="t('pipeline.confirmContactNoContactConfirm')"
        @confirm="() => applyAction('contact')"
      />
    </template>
  </PageContainer>
</template>
