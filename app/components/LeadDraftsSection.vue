<script setup lang="ts">
import type { Draft, DraftType, Template } from '~/types/drafting'
import type { Mailbox } from '~/types/mailbox'

const props = defineProps<{
  leadId: string
  /** Paire de la piste (ex. « en>fr ») : la langue par défaut du message est celle du prospect. */
  languagePair: string
  /** Génération proposée uniquement sur une piste encore travaillable. */
  canGenerate: boolean
}>()

/** La génération alimente le journal : le parent rafraîchit sa timeline. */
const emit = defineEmits<{ activity: [] }>()

const { t, locale } = useI18n()
const draftsApi = useDrafts()
const draftLabels = useDraftLabels()
const toast = useToast()

const { data: drafts, refresh: refreshDrafts } = await useAsyncData<Draft[]>(
  `lead-${props.leadId}-drafts`,
  () => draftsApi.forLead(props.leadId),
  { server: false, default: () => [] },
)

// La boîte connectée conditionne le bouton Envoyer (clé partagée avec les Réglages).
const mailboxApi = useMailbox()
const { data: mailbox } = await useAsyncData<Mailbox | null>(
  'mailbox',
  () => mailboxApi.get(),
  { server: false, default: () => null },
)
const canSend = computed(() => mailbox.value?.status === 'CONNECTED')

// ----- Génération (modale) -----
const generateOpen = ref(false)
const genType = ref<DraftType>('APPLICATION_EMAIL')
const genLanguage = ref('fr')
const genTemplateId = ref('NONE')
const generating = ref(false)
const templates = ref<Template[]>([])

const languageOptions = computed(() => {
  const fromPair = props.languagePair.split('>').map(code => code.trim()).filter(code => code.length === 2)
  return [...new Set([...fromPair.reverse(), 'fr', 'en', 'es'])]
    .map(value => ({ value, label: value.toUpperCase() }))
})

const templateOptions = computed(() => [
  { value: 'NONE', label: t('drafts.modal.noTemplate') },
  ...templates.value
    .filter(template => template.type === genType.value)
    .map(template => ({ value: template.id, label: template.name })),
])

async function openGenerator(): Promise<void> {
  genType.value = 'APPLICATION_EMAIL'
  genLanguage.value = props.languagePair.split('>')[1]?.trim() ?? 'fr'
  genTemplateId.value = 'NONE'
  generateOpen.value = true
  try {
    templates.value = await draftsApi.templates()
  }
  catch {
    templates.value = []
  }
}

async function submitGenerate(): Promise<void> {
  generating.value = true
  try {
    await draftsApi.generate(props.leadId, {
      type: genType.value,
      targetLanguage: genLanguage.value,
      templateId: genTemplateId.value === 'NONE' ? null : genTemplateId.value,
    })
    generateOpen.value = false
    await refreshDrafts()
    startPolling()
    emit('activity')
    toast.add({ title: t('drafts.toasts.requested'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    generating.value = false
  }
}

// ----- Rattrapage asynchrone (le worker génère) -----
const MAX_POLL_ATTEMPTS = 8
let draftPollTimer: ReturnType<typeof setTimeout> | null = null
/** Vrai quand le rattrapage a abandonné alors qu'une génération court toujours. */
const pollExhausted = ref(false)

const hasGenerating = computed(() => drafts.value.some(draft => draft.status === 'GENERATING'))

function startPolling(attempt = 0): void {
  pollExhausted.value = false
  if (attempt >= MAX_POLL_ATTEMPTS) {
    // On le DIT au lieu d'abandonner en silence : bouton « Actualiser » visible.
    pollExhausted.value = hasGenerating.value
    return
  }
  if (draftPollTimer) clearTimeout(draftPollTimer)
  draftPollTimer = setTimeout(() => {
    void refreshDrafts().then(() => {
      syncEditorWithList()
      if (hasGenerating.value) {
        startPolling(attempt + 1)
      }
      else {
        emit('activity')
      }
    })
  }, 1500)
}

async function manualRefresh(): Promise<void> {
  await refreshDrafts()
  syncEditorWithList()
  if (hasGenerating.value) startPolling()
  else emit('activity')
}

onUnmounted(() => {
  if (draftPollTimer) clearTimeout(draftPollTimer)
})

// « Rédiger la relance » depuis Aujourd'hui : générateur pré-ouvert en relance
// (draft-first : la relance se relit comme le reste avant de partir dans le fil).
const route = useRoute()
onMounted(() => {
  if (route.query.draft === 'follow-up' && props.canGenerate) {
    void openGenerator().then(() => {
      genType.value = 'FOLLOW_UP_EMAIL'
    })
  }
})

// ----- Éditeur (enfant) : le brouillon ouvert, réaligné après chaque refresh -----
const editingDraft = ref<Draft | null>(null)

function openDraft(draft: Draft): void {
  editingDraft.value = draft
  if (draft.status === 'GENERATING') startPolling()
}

/** Après un refresh, réaligne l'éditeur ouvert sur l'état serveur (READY/FAILED). */
function syncEditorWithList(): void {
  if (!editingDraft.value) return
  editingDraft.value = drafts.value.find(draft => draft.id === editingDraft.value?.id) ?? null
}

/** L'éditeur a modifié le brouillon : rafraîchir, réaligner, relancer le polling si besoin. */
async function onEditorChanged(): Promise<void> {
  await refreshDrafts()
  syncEditorWithList()
  if (hasGenerating.value) startPolling()
  emit('activity')
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <section class="mt-10">
    <div class="flex items-center gap-3 flex-wrap">
      <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">{{ t('drafts.title') }}</p>
      <UButton
        v-if="canGenerate"
        class="ml-auto"
        size="xs"
        variant="outline"
        icon="i-lucide-feather"
        @click="openGenerator"
      >
        {{ t('drafts.generate') }}
      </UButton>
    </div>

    <!-- Le rattrapage a abandonné : on le dit et on rend la main. -->
    <UAlert
      v-if="pollExhausted"
      class="mt-3"
      color="warning"
      variant="soft"
      icon="i-lucide-hourglass"
      :title="t('drafts.stillGenerating')"
    >
      <template #actions>
        <UButton size="xs" variant="soft" color="warning" icon="i-lucide-refresh-cw" @click="manualRefresh">
          {{ t('drafts.refresh') }}
        </UButton>
      </template>
    </UAlert>

    <ul v-if="drafts.length" class="mt-3 border border-default rounded-xl divide-y divide-[var(--ui-border)]">
      <li v-for="draft in drafts" :key="draft.id">
        <button
          type="button"
          class="w-full p-3 flex items-center gap-3 text-left hover:bg-elevated/60 rounded-xl"
          @click="() => openDraft(draft)"
        >
          <UIcon
            :name="draft.status === 'GENERATING' ? 'i-lucide-loader-circle' : 'i-lucide-feather'"
            :class="['shrink-0', draft.status === 'GENERATING' ? 'animate-spin text-warning' : 'text-primary']"
            aria-hidden="true"
          />
          <div class="min-w-0 flex-1">
            <div class="text-sm font-medium flex items-center gap-2 flex-wrap">
              {{ draftLabels.typeLabel(draft.type) }}
              <span class="text-xs text-dimmed font-mono uppercase">{{ draft.targetLanguage }}</span>
              <UBadge :color="draftLabels.statusColor(draft.status)" variant="soft" size="sm">
                {{ draftLabels.statusLabel(draft.status) }}
              </UBadge>
            </div>
            <p v-if="draft.status === 'READY' && draft.subject" class="text-xs text-muted truncate mt-0.5">{{ draft.subject }}</p>
            <p v-else-if="draft.status === 'FAILED' && draft.failureReason" class="text-xs text-error mt-0.5">
              {{ draftLabels.failureLabel(draft.failureReason) }}
            </p>
          </div>
          <time class="text-xs text-dimmed shrink-0 tabular-nums" :datetime="draft.updatedAt">
            {{ formatDate(draft.updatedAt) }}
          </time>
        </button>
      </li>
    </ul>
    <p v-else class="mt-3 p-4 text-sm text-muted border border-default rounded-xl">
      {{ t('drafts.empty') }}
    </p>

    <!-- Modale « Générer un brouillon » -->
    <UModal v-model:open="generateOpen" :title="t('drafts.modal.title')" :description="t('drafts.modal.description')">
      <template #body>
        <div class="flex flex-col gap-4">
          <UFormField :label="t('drafts.modal.typeLabel')" required>
            <USelect v-model="genType" :items="draftLabels.typeOptions.value" class="w-full" />
          </UFormField>
          <UFormField :label="t('drafts.modal.languageLabel')" :hint="t('drafts.modal.languageHint')" required>
            <USelect v-model="genLanguage" :items="languageOptions" class="w-full" />
          </UFormField>
          <UFormField :label="t('drafts.modal.templateLabel')">
            <USelect v-model="genTemplateId" :items="templateOptions" class="w-full" />
          </UFormField>
        </div>
      </template>
      <template #footer>
        <div class="flex gap-2 justify-end w-full">
          <UButton color="neutral" variant="ghost" @click="() => { generateOpen = false }">{{ t('actions.cancel') }}</UButton>
          <UButton :loading="generating" icon="i-lucide-feather" @click="submitGenerate">
            {{ t('drafts.modal.submit') }}
          </UButton>
        </div>
      </template>
    </UModal>

    <!-- Éditeur de brouillon (relecture humaine, draft-first) -->
    <LeadDraftEditor
      v-model:draft="editingDraft"
      :can-send="canSend"
      :mailbox-email="mailbox?.emailAddress ?? ''"
      :poll-exhausted="pollExhausted"
      @changed="onEditorChanged"
      @refresh="manualRefresh"
    />
  </section>
</template>
