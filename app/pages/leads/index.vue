<script setup lang="ts">
import type { Lead, LeadStatus } from '~/types/leads'

const { t } = useI18n()
const { statusLabel, priorityLabel, pairLabel } = useLeadLabels()
const { segmentLabel } = useDirectoryLabels()
const leads = useLeads()

const { data: allLeads, status } = await useAsyncData<Lead[]>(
  'leads',
  () => leads.list(),
  { server: false, default: () => [] },
)

const loading = computed(() => status.value === 'idle' || status.value === 'pending')

/** Colonnes du kanban (tous les statuts du pipeline). */
const COLUMNS: LeadStatus[] = ['TO_CONTACT', 'CONTACTED', 'FOLLOWED_UP', 'IN_DISCUSSION', 'SAMPLE_TEST', 'PAUSED', 'WON', 'LOST']

const byStatus = computed(() => {
  const groups = new Map<LeadStatus, Lead[]>(COLUMNS.map(s => [s, []]))
  for (const lead of allLeads.value) {
    groups.get(lead.status)?.push(lead)
  }
  return groups
})

const priorityDot: Record<string, string> = {
  HIGH: 'bg-error',
  MEDIUM: 'bg-warning',
  LOW: 'bg-elevated',
}
</script>

<template>
  <PageContainer width="full">
    <PageHeader :eyebrow="t('pipeline.eyebrow')" :title="t('pipeline.title')">
      <template #actions>
        <UButton icon="i-lucide-plus" to="/leads/new">{{ t('pipeline.newLead') }}</UButton>
      </template>
    </PageHeader>

    <div v-if="loading" role="status" class="mt-6 flex gap-3">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <div v-for="i in 6" :key="i" class="flex-1 min-w-40 flex flex-col gap-2">
        <USkeleton class="h-4 w-24 rounded" />
        <USkeleton class="h-24 rounded-xl" />
        <USkeleton class="h-24 rounded-xl" />
      </div>
    </div>

    <div v-else-if="!allLeads.length" class="mt-6 py-16 flex flex-col items-center gap-3 text-center border border-default rounded-xl">
      <p class="text-muted max-w-md">{{ t('pipeline.empty') }}</p>
      <UButton icon="i-lucide-plus" to="/leads/new">{{ t('pipeline.new.title') }}</UButton>
    </div>

    <!-- Kanban : colonnes par statut. Grand écran : elles se répartissent pour
         montrer tout le pipeline d'un coup d'œil ; en dessous, défilement
         horizontal avec accroche (scroll-snap) une colonne à la fois. -->
    <div v-else class="mt-6 overflow-x-auto pb-4 snap-x snap-mandatory sm:snap-none">
      <div class="flex gap-3 rise-stagger">
        <section
          v-for="column in COLUMNS"
          :key="column"
          class="flex-1 min-w-[78vw] sm:min-w-40 snap-start"
          :aria-label="statusLabel(column)"
        >
          <h2 class="text-[11px] uppercase tracking-wider text-dimmed font-semibold px-1 flex items-center gap-2">
            {{ statusLabel(column) }}
            <span class="font-mono tabular-nums text-muted">{{ byStatus.get(column)?.length ?? 0 }}</span>
          </h2>
          <ul class="mt-2 flex flex-col gap-2 min-h-24 rounded-xl border border-default bg-elevated/30 p-2">
            <li v-for="lead in byStatus.get(column)" :key="lead.id">
              <NuxtLink
                :to="`/leads/${lead.id}`"
                class="block border border-default rounded-lg p-3 bg-default hover:bg-elevated focus-visible:outline-2 focus-visible:outline-primary motion-safe:transition-transform motion-safe:hover:-translate-y-0.5"
              >
                <div class="flex items-center gap-2">
                  <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="priorityDot[lead.priority]"
                    :title="priorityLabel(lead.priority)"
                    aria-hidden="true"
                  />
                  <span class="font-medium text-sm truncate">{{ lead.organizationName }}</span>
                </div>
                <div class="mt-1.5 flex items-center gap-1.5 flex-wrap">
                  <LangStamp :code="pairLabel(lead.languagePair)" />
                  <UBadge color="neutral" variant="soft" size="sm">{{ segmentLabel(lead.segment) }}</UBadge>
                </div>
                <span class="sr-only">{{ priorityLabel(lead.priority) }}</span>
              </NuxtLink>
            </li>
          </ul>
        </section>
      </div>
    </div>
  </PageContainer>
</template>
