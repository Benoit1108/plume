<script setup lang="ts">
import type { Lead, LeadStatus } from '~/types/leads'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { statusLabel, priorityLabel, pairLabel } = useLeadLabels()
const { segmentLabel, segmentOptions } = useDirectoryLabels()
const leadsApi = useLeads()
const toast = useToast()

const { data: allLeads, status, refresh } = await useAsyncData<Lead[]>(
  'leads',
  () => leadsApi.list(),
  { server: false, default: () => [] },
)

const loading = computed(() => status.value === 'idle' || status.value === 'pending')

/** Colonnes du kanban (tous les statuts du pipeline). */
const COLUMNS: LeadStatus[] = ['TO_CONTACT', 'CONTACTED', 'FOLLOWED_UP', 'IN_DISCUSSION', 'SAMPLE_TEST', 'PAUSED', 'WON', 'LOST']

// --- Filtre par segment (drill-down depuis le tableau de bord via ?segment=) ---
const SEGMENT_ALL = 'ALL'
const segment = ref(typeof route.query.segment === 'string' ? route.query.segment : SEGMENT_ALL)
const segmentFilterItems = computed(() => [
  { value: SEGMENT_ALL, label: t('pipeline.filterAllSegments') },
  ...segmentOptions.value,
])
watch(segment, (value) => {
  void router.replace({ query: value === SEGMENT_ALL ? {} : { segment: value } })
})

const visibleLeads = computed(() =>
  segment.value === SEGMENT_ALL
    ? allLeads.value
    : allLeads.value.filter(lead => lead.segment === segment.value),
)

const byStatus = computed(() => {
  const groups = new Map<LeadStatus, Lead[]>(COLUMNS.map(s => [s, []]))
  for (const lead of visibleLeads.value) {
    groups.get(lead.status)?.push(lead)
  }
  return groups
})

const priorityDot: Record<string, string> = {
  HIGH: 'bg-error',
  MEDIUM: 'bg-warning',
  LOW: 'bg-elevated',
}

// --- Glisser-déposer : déplacer une piste d'une colonne à l'autre. ---
// Logique de légalité des transitions : util pur `~/utils/kanban` (testé isolément).
const dragging = ref<Lead | null>(null)
const dragOver = ref<LeadStatus | null>(null)
const moving = ref<string | null>(null)

function isLegalTarget(targetStatus: LeadStatus): boolean {
  return dragging.value ? kanbanActionFor(dragging.value, targetStatus) !== null : false
}

function onDragStart(lead: Lead, event: DragEvent): void {
  dragging.value = lead
  if (event.dataTransfer) {
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', lead.id)
  }
}

function onDragEnd(): void {
  dragging.value = null
  dragOver.value = null
}

async function onDrop(targetStatus: LeadStatus): Promise<void> {
  const lead = dragging.value
  dragOver.value = null
  dragging.value = null
  if (!lead) return

  const action = kanbanActionFor(lead, targetStatus)
  if (!action) {
    if (lead.status !== targetStatus) toast.add({ title: t('pipeline.dnd.illegal'), color: 'warning' })
    return
  }

  const previousStatus = lead.status
  lead.status = targetStatus // déplacement optimiste : la carte change de colonne aussitôt
  moving.value = lead.id
  try {
    await leadsApi.transition(lead.id, action)
    await refresh()
    toast.add({ title: t('pipeline.toasts.updated'), color: 'success' })
  }
  catch (error) {
    lead.status = previousStatus // rollback visuel si l'API refuse
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    moving.value = null
  }
}
</script>

<template>
  <PageContainer width="full">
    <PageHeader :eyebrow="t('pipeline.eyebrow')" :title="t('pipeline.title')">
      <template #actions>
        <USelect
          v-model="segment"
          :items="segmentFilterItems"
          value-key="value"
          label-key="label"
          :aria-label="t('pipeline.filterBySegment')"
          class="w-48"
        />
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

    <!-- Kanban : colonnes par statut. Grand écran : elles se répartissent pour montrer
         tout le pipeline d'un coup d'œil ; en dessous, défilement horizontal avec accroche.
         Glisser-déposer : les colonnes atteignables s'allument, les autres s'estompent. -->
    <div v-else class="mt-6 overflow-x-auto pb-4 snap-x snap-mandatory sm:snap-none">
      <div class="flex gap-3 rise-stagger">
        <section
          v-for="column in COLUMNS"
          :key="column"
          class="flex-1 min-w-[78vw] sm:min-w-40 snap-start rounded-xl motion-safe:transition-[opacity,box-shadow]"
          :class="[
            dragging && isLegalTarget(column) ? 'ring-2 ring-primary/60' : '',
            dragging && !isLegalTarget(column) && dragging.status !== column ? 'opacity-40' : '',
          ]"
          :aria-label="statusLabel(column)"
          @dragover.prevent="dragOver = isLegalTarget(column) ? column : null"
          @drop.prevent="onDrop(column)"
        >
          <h2 class="text-[11px] uppercase tracking-wider text-dimmed font-semibold px-1 flex items-center gap-2">
            {{ statusLabel(column) }}
            <span class="font-mono tabular-nums text-muted">{{ byStatus.get(column)?.length ?? 0 }}</span>
          </h2>
          <ul
            class="mt-2 flex flex-col gap-2 min-h-24 rounded-xl border border-default p-2 motion-safe:transition-colors"
            :class="dragOver === column ? 'bg-primary/10 border-primary/50' : 'bg-elevated/30'"
          >
            <li v-for="lead in byStatus.get(column)" :key="lead.id">
              <NuxtLink
                :to="`/leads/${lead.id}`"
                draggable="true"
                class="block border border-default rounded-lg p-3 bg-default hover:bg-elevated focus-visible:outline-2 focus-visible:outline-primary cursor-grab active:cursor-grabbing motion-safe:transition-transform motion-safe:hover:-translate-y-0.5"
                :class="moving === lead.id ? 'opacity-50 pointer-events-none' : ''"
                @dragstart="onDragStart(lead, $event)"
                @dragend="onDragEnd"
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
