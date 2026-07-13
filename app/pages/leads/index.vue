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

/** Colonnes du kanban — FOLLOWED_UP rejoindra le tableau en M1.3 (relances). */
const COLUMNS: LeadStatus[] = ['TO_CONTACT', 'CONTACTED', 'IN_DISCUSSION', 'SAMPLE_TEST', 'PAUSED', 'WON', 'LOST']

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
  <UContainer class="py-8 max-w-none">
    <div class="flex items-end gap-4 flex-wrap">
      <div>
        <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">{{ t('pipeline.eyebrow') }}</p>
        <h1 class="font-serif text-3xl font-semibold mt-1">{{ t('pipeline.title') }}</h1>
      </div>
      <div class="ml-auto">
        <UButton icon="i-lucide-plus" to="/leads/new">{{ t('pipeline.newLead') }}</UButton>
      </div>
    </div>

    <div v-if="loading" class="py-12 text-center text-dimmed">{{ t('common.loading') }}</div>

    <div v-else-if="!allLeads.length" class="mt-6 py-12 text-center text-muted border border-default rounded-xl">
      {{ t('pipeline.empty') }}
    </div>

    <!-- Kanban : défilement horizontal, colonnes par statut -->
    <div v-else class="mt-6 overflow-x-auto pb-4">
      <div class="flex gap-4 min-w-max">
        <section
          v-for="column in COLUMNS"
          :key="column"
          class="w-64 shrink-0"
          :aria-label="statusLabel(column)"
        >
          <h2 class="text-[11px] uppercase tracking-wider text-dimmed font-semibold px-1 flex items-center gap-2">
            {{ statusLabel(column) }}
            <span class="font-mono tabular-nums text-muted">{{ byStatus.get(column)?.length ?? 0 }}</span>
          </h2>
          <ul class="mt-2 flex flex-col gap-2 min-h-16 rounded-lg">
            <li v-for="lead in byStatus.get(column)" :key="lead.id">
              <NuxtLink
                :to="`/leads/${lead.id}`"
                class="block border border-default rounded-lg p-3 bg-elevated/40 hover:bg-elevated focus-visible:outline-2 focus-visible:outline-primary"
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
  </UContainer>
</template>
