<script setup lang="ts">
import type { Organization, OrganizationType, Segment } from '~/types/directory'
import type { LeadPriority } from '~/types/leads'
import type { CandidateLead } from '~/types/sourcing'

const { t, locale } = useI18n()
const { sourceLabel, pairLabel, priorityOptions } = useLeadLabels()
const { typeOptions, segmentOptions } = useDirectoryLabels()
const sourcing = useSourcing()
const directory = useDirectory()
const toast = useToast()

// Après un tri, l'élément traité disparaît : on ramène le focus en tête de page
// (sinon il retombe sur <body> et l'utilisateur clavier perd sa place).
const topRef = ref<HTMLElement | null>(null)
function focusTop(): void {
  void nextTick(() => topRef.value?.focus())
}

const queryClient = useQueryClient()
// queryFn appelle sourcing.queue() qui met aussi à jour le compteur partagé du badge de nav.
const { data: candidatesData, isPending: loading } = useQuery({ queryKey: queryKeys.candidateQueue, queryFn: () => sourcing.queue() })
const candidates = computed<CandidateLead[]>(() => candidatesData.value ?? [])
async function refresh(): Promise<void> { await queryClient.invalidateQueries({ queryKey: queryKeys.candidateQueue }) }

// Organisations existantes (pour la fusion).
const { data: orgsData } = useQuery({ queryKey: queryKeys.organizations, queryFn: () => directory.list() })
const organizations = computed<Organization[]>(() => orgsData.value ?? [])
const organizationOptions = computed(() => organizations.value.map(o => ({ value: o.id, label: o.name })))

// --- Tri : accepter (nouvelle organisation) / fusionner (organisation existante) ---
const triaging = ref<{ candidate: CandidateLead, mode: 'accept' | 'merge' } | null>(null)
const triageOpen = computed({
  get: () => triaging.value !== null,
  set: (open: boolean) => {
    if (!open) triaging.value = null
  },
})
const submitting = ref(false)
const form = reactive({
  organizationName: '',
  organizationType: 'PUBLISHER' as OrganizationType,
  organizationId: '',
  languagePair: 'en>fr',
  segment: 'PUBLISHING' as Segment,
  priority: 'MEDIUM' as LeadPriority,
  website: '',
})

function openAccept(candidate: CandidateLead): void {
  form.organizationName = candidate.organizationName ?? ''
  form.organizationType = 'PUBLISHER'
  form.languagePair = candidate.languagePair ?? 'en>fr'
  form.segment = 'PUBLISHING'
  form.priority = 'MEDIUM'
  form.website = safeUrl(candidate.url) ?? ''
  triaging.value = { candidate, mode: 'accept' }
}

function openMerge(candidate: CandidateLead): void {
  form.organizationId = organizations.value[0]?.id ?? ''
  form.languagePair = candidate.languagePair ?? 'en>fr'
  form.segment = 'PUBLISHING'
  form.priority = 'MEDIUM'
  triaging.value = { candidate, mode: 'merge' }
}

const pairValid = computed(() => /^[a-z]{2}>[a-z]{2}$/i.test(form.languagePair.trim()))
const canSubmit = computed(() =>
  triaging.value?.mode === 'accept'
    ? form.organizationName.trim() !== '' && pairValid.value
    : form.organizationId !== '' && pairValid.value,
)

async function submitTriage(): Promise<void> {
  if (!triaging.value) return
  const { candidate, mode } = triaging.value
  submitting.value = true
  try {
    if (mode === 'accept') {
      await sourcing.accept(candidate.id, {
        organizationName: form.organizationName.trim(),
        organizationType: form.organizationType,
        languagePair: form.languagePair.trim().toLowerCase(),
        segment: form.segment,
        priority: form.priority,
        website: form.website.trim() || null,
      })
    }
    else {
      await sourcing.merge(candidate.id, {
        organizationId: form.organizationId,
        languagePair: form.languagePair.trim().toLowerCase(),
        segment: form.segment,
        priority: form.priority,
      })
    }
    triaging.value = null
    // La promotion crée une piste (+ éventuellement une organisation) : on rafraîchit les 3.
    await Promise.all([
      queryClient.invalidateQueries({ queryKey: queryKeys.candidateQueue }),
      queryClient.invalidateQueries({ queryKey: queryKeys.leads }),
      queryClient.invalidateQueries({ queryKey: queryKeys.organizations }),
    ])
    focusTop()
    toast.add({ title: t('sourcing.toasts.promoted'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: isConflict(error) ? t('sourcing.errors.conflict') : errorToastTitle(t, error), color: 'error' })
  }
  finally {
    submitting.value = false
  }
}

// --- Rejeter ---
const rejecting = ref<CandidateLead | null>(null)
const confirmReject = computed({
  get: () => rejecting.value !== null,
  set: (open: boolean) => {
    if (!open) rejecting.value = null
  },
})

async function doReject(): Promise<void> {
  if (!rejecting.value) return
  try {
    await sourcing.reject(rejecting.value.id)
    rejecting.value = null
    await refresh()
    focusTop()
    toast.add({ title: t('sourcing.toasts.rejected'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}

// --- Relever les sources (ingestion des annonces, tenant courant) ---
// La relève est ASYNCHRONE (202, worker) : on rafraîchit la file plusieurs fois au fil de
// l'ingestion (l'I/O RSS peut prendre quelques secondes côté worker).
const polling = ref(false)
const pollCatchUpTimers: ReturnType<typeof setTimeout>[] = []

function schedulePollCatchUp(): void {
  pollCatchUpTimers.forEach(clearTimeout)
  pollCatchUpTimers.length = 0
  for (const delay of [1000, 3000, 6000, 10000]) {
    pollCatchUpTimers.push(setTimeout(() => { void refresh() }, delay))
  }
}
onUnmounted(() => pollCatchUpTimers.forEach(clearTimeout))

async function doPoll(): Promise<void> {
  polling.value = true
  try {
    await sourcing.poll()
    toast.add({ title: t('sourcing.toasts.polled'), color: 'success' })
    schedulePollCatchUp()
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    polling.value = false
  }
}

function formatDate(iso?: string | null): string {
  return iso ? new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' }) : ''
}

/** Défense en profondeur anti-XSS : n'accepter un lien d'annonce que s'il est http(s). */
function safeUrl(url?: string | null): string | null {
  return url && /^https?:\/\//i.test(url) ? url : null
}
</script>

<template>
  <PageContainer width="reading">
    <div ref="topRef" tabindex="-1" class="outline-none">
      <PageHeader :eyebrow="t('sourcing.eyebrow')" :title="t('sourcing.title')">
        <template #subtitle>
          <p class="mt-1 text-muted">{{ t('sourcing.intro') }}</p>
        </template>
        <template #actions>
          <!-- Masqué en état vide : le CTA de l'état vide évite le doublon de même libellé. -->
          <UButton v-if="candidates.length" icon="i-lucide-refresh-cw" variant="outline" :loading="polling" @click="doPoll">
            {{ t('sourcing.actions.poll') }}
          </UButton>
        </template>
      </PageHeader>
    </div>

    <div v-if="loading" role="status" class="mt-6 flex flex-col gap-3">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <USkeleton v-for="i in 3" :key="i" class="h-28 rounded-xl" />
    </div>

    <div v-else-if="!candidates.length" class="mt-6 py-16 flex flex-col items-center gap-3 text-center border border-default rounded-xl">
      <UIcon name="i-lucide-inbox" class="size-8 text-dimmed" aria-hidden="true" />
      <p class="text-muted max-w-md">{{ t('sourcing.empty') }}</p>
      <UButton icon="i-lucide-refresh-cw" variant="outline" :loading="polling" @click="doPoll">
        {{ t('sourcing.actions.poll') }}
      </UButton>
    </div>

    <ul v-else class="mt-6 flex flex-col gap-3 rise-stagger">
      <li v-for="candidate in candidates" :key="candidate.id" class="border border-default rounded-xl p-4 bg-elevated/40">
        <div class="flex items-start gap-3 flex-wrap">
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2 flex-wrap">
              <UBadge color="neutral" variant="soft" size="sm">{{ sourceLabel(candidate.source) }}</UBadge>
              <span class="font-medium">{{ candidate.title }}</span>
            </div>
            <div class="mt-1.5 text-sm text-muted flex items-center gap-2 flex-wrap">
              <span v-if="candidate.organizationName" class="font-medium text-default">{{ candidate.organizationName }}</span>
              <LangStamp v-if="candidate.languagePair" :code="pairLabel(candidate.languagePair)" />
              <span v-if="candidate.postedAt" class="text-xs text-dimmed">{{ formatDate(candidate.postedAt) }}</span>
            </div>
            <p v-if="candidate.excerpt" class="mt-2 text-sm text-muted line-clamp-2">{{ candidate.excerpt }}</p>
            <a
              v-if="safeUrl(candidate.url)"
              :href="safeUrl(candidate.url) ?? undefined"
              target="_blank"
              rel="noopener"
              class="mt-2 inline-flex items-center gap-1 text-xs text-primary hover:underline"
            >
              <UIcon name="i-lucide-external-link" aria-hidden="true" /> {{ t('sourcing.viewAnnounce') }}
            </a>
          </div>
          <div class="flex gap-2 flex-wrap shrink-0">
            <UButton size="sm" icon="i-lucide-check" @click="() => openAccept(candidate)">
              {{ t('sourcing.actions.accept') }}
            </UButton>
            <UButton
              size="sm"
              variant="outline"
              icon="i-lucide-git-merge"
              :disabled="!organizations.length"
              :title="!organizations.length ? t('sourcing.mergeNoOrg') : undefined"
              @click="() => openMerge(candidate)"
            >
              {{ t('sourcing.actions.merge') }}
            </UButton>
            <UButton
              size="sm"
              variant="ghost"
              color="error"
              icon="i-lucide-x"
              :aria-label="t('sourcing.actions.reject')"
              @click="() => { rejecting = candidate }"
            />
          </div>
        </div>
      </li>
    </ul>

    <!-- Accepter (nouvelle organisation) / Fusionner (organisation existante) -->
    <UModal
      v-model:open="triageOpen"
      :title="triaging?.mode === 'accept' ? t('sourcing.acceptTitle') : t('sourcing.mergeTitle')"
      :description="triaging?.mode === 'accept' ? t('sourcing.acceptDescription') : t('sourcing.mergeDescription')"
    >
      <template #body>
        <div v-if="triaging" class="flex flex-col gap-4">
          <template v-if="triaging.mode === 'accept'">
            <UFormField :label="t('sourcing.form.organizationName')" required>
              <UInput v-model="form.organizationName" class="w-full" maxlength="200" />
            </UFormField>
            <UFormField :label="t('sourcing.form.organizationType')">
              <USelect v-model="form.organizationType" :items="typeOptions" value-key="value" label-key="label" class="w-full" />
            </UFormField>
          </template>
          <UFormField v-else :label="t('sourcing.form.organization')" required>
            <USelect v-model="form.organizationId" :items="organizationOptions" value-key="value" label-key="label" class="w-full" />
          </UFormField>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <UFormField :label="t('sourcing.form.languagePair')" required>
              <UInput v-model="form.languagePair" class="w-full font-mono" placeholder="en>fr" />
            </UFormField>
            <UFormField :label="t('sourcing.form.segment')">
              <USelect v-model="form.segment" :items="segmentOptions" value-key="value" label-key="label" class="w-full" />
            </UFormField>
            <UFormField :label="t('sourcing.form.priority')">
              <USelect v-model="form.priority" :items="priorityOptions" value-key="value" label-key="label" class="w-full" />
            </UFormField>
          </div>

          <UFormField v-if="triaging.mode === 'accept'" :label="t('sourcing.form.website')">
            <UInput v-model="form.website" class="w-full" placeholder="https://…" />
          </UFormField>
        </div>
      </template>
      <template #footer>
        <div class="flex gap-2 justify-end w-full">
          <UButton color="neutral" variant="ghost" @click="() => { triaging = null }">{{ t('actions.cancel') }}</UButton>
          <UButton :loading="submitting" :disabled="!canSubmit" @click="submitTriage">
            {{ triaging?.mode === 'accept' ? t('sourcing.actions.accept') : t('sourcing.actions.merge') }}
          </UButton>
        </div>
      </template>
    </UModal>

    <ConfirmDialog
      v-model:open="confirmReject"
      :title="t('sourcing.confirmRejectTitle')"
      :description="t('sourcing.confirmRejectBody', { title: rejecting?.title ?? '' })"
      :confirm-label="t('sourcing.actions.reject')"
      danger
      @confirm="doReject"
    />
  </PageContainer>
</template>
