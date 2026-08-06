<script setup lang="ts">
import type { Lead, LeadStatus } from '~/types/domain/leads'

const { t } = useI18n()
const route = useRoute()
const router = useRouter()
const { statusLabel, priorityLabel, priorityInitial, pairLabel } = useLeadLabels()
const { segmentLabel, segmentOptions } = useDirectoryLabels()
const leadsApi = useLeads()
const toast = useToast()

const queryClient = useQueryClient()
const { data: allLeadsData, isPending: loading, isError, refetch } = useQuery({ queryKey: queryKeys.leads, queryFn: () => leadsApi.list() })
const allLeads = computed<Lead[]>(() => allLeadsData.value ?? [])

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

// Priorité : la couleur SEULE ne suffit pas (WCAG 1.4.1 — revue UX-P2e). Un daltonien voyant ne
// distinguait rien : le `sr-only` sert les lecteurs d'écran, pas les yeux. On ajoute une initiale
// dans la pastille, qui reste discrète mais lisible.
const priorityDot: Record<string, string> = {
  HIGH: 'bg-error text-inverted',
  MEDIUM: 'bg-warning text-inverted',
  LOW: 'bg-elevated text-muted',
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
    await invalidateLeadRelated(queryClient)
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

    <!-- `overflow-x-auto` comme la vraie liste : sans lui, les 6 colonnes de squelettes (min 160 px)
         font défiler la PAGE sur mobile pendant tout le chargement (revue UX-P1, second cas). -->
    <div v-if="loading" role="status" class="mt-6 flex gap-3 overflow-x-auto pb-4">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <div v-for="i in 6" :key="i" class="flex-1 min-w-40 shrink-0 flex flex-col gap-2">
        <USkeleton class="h-4 w-24 rounded" />
        <USkeleton class="h-24 rounded-xl" />
        <USkeleton class="h-24 rounded-xl" />
      </div>
    </div>

    <QueryError v-else-if="isError" class="mt-6" @retry="() => { void refetch() }" />

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
          class="flex-1 min-w-[78vw] sm:min-w-40 snap-start rounded-xl motion-safe:transition-opacity"
          :class="dragging && !isLegalTarget(column) && dragging.status !== column ? 'opacity-40' : ''"
          :aria-label="statusLabel(column)"
          @dragover.prevent="dragOver = isLegalTarget(column) ? column : null"
          @drop.prevent="onDrop(column)"
        >
          <h2 class="text-[11px] uppercase tracking-wider text-dimmed font-semibold px-1 flex items-center gap-2">
            {{ statusLabel(column) }}
            <span class="font-mono tabular-nums text-muted">{{ byStatus.get(column)?.length ?? 0 }}</span>
          </h2>
          <!-- Le surlignage porte sur la ZONE DE DÉPÔT, pas sur la colonne entière : le contour
               enveloppait aussi le titre, qui s'y retrouvait collé — d'où l'impression d'un cadre
               tronqué en haut. Deux états distincts : atteignable, puis survolée. -->
          <ul
            class="mt-2 flex flex-col gap-2 min-h-24 rounded-xl border border-default p-2 motion-safe:transition-colors"
            :class="[
              dragOver === column ? 'bg-primary/10 border-primary ring-2 ring-primary' : 'bg-elevated/30',
              dragging && isLegalTarget(column) && dragOver !== column ? 'border-primary/40 ring-1 ring-primary/30' : '',
            ]"
          >
            <li v-for="lead in byStatus.get(column)" :key="lead.id">
              <!-- `relative` : le libellé de priorité en `sr-only` est ABSOLU. Sans ancêtre
                   positionné, son bloc conteneur est le document — il échappe alors au clip du
                   scroller horizontal et fait défiler TOUTE la page sur mobile (revue UX-P1). -->
              <NuxtLink
                :to="`/leads/${lead.id}`"
                draggable="true"
                class="relative block border border-default rounded-lg p-3 bg-default hover:bg-elevated focus-visible:outline-2 focus-visible:outline-primary cursor-grab active:cursor-grabbing motion-safe:transition-transform motion-safe:hover:-translate-y-0.5"
                :class="moving === lead.id ? 'opacity-50 pointer-events-none' : ''"
                @dragstart="onDragStart(lead, $event)"
                @dragend="onDragEnd"
              >
                <div class="flex items-center gap-2">
                  <span
                    class="w-4 h-4 rounded-full shrink-0 inline-flex items-center justify-center text-[9px] font-semibold leading-none"
                    :class="priorityDot[lead.priority]"
                    :title="priorityLabel(lead.priority)"
                    aria-hidden="true"
                  >{{ priorityInitial(lead.priority) }}</span>
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
