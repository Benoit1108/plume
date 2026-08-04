<script setup lang="ts">
import type { Organization, OrganizationType, Segment } from '~/types/directory'
import type { LeadPriority } from '~/types/leads'
import type { CandidateLead } from '~/types/sourcing'

/**
 * Tri d'un candidat : accepter (nouvelle organisation) ou fusionner (organisation existante).
 * Encapsule le formulaire, le dédoublonnage suggéré et la promotion. Ouvert par le parent via
 * `open(candidate, mode)` (exposé) ; émet `promoted` après une promotion réussie (le parent recadre
 * le focus). Auto-suffisant côté données (invalidations).
 */
const props = defineProps<{ organizations: Organization[] }>()
const emit = defineEmits<{ promoted: [] }>()

const { t } = useI18n()
const { priorityOptions } = useLeadLabels()
const { typeOptions, segmentOptions } = useDirectoryLabels()
const sourcing = useSourcing()
const toast = useToast()
const queryClient = useQueryClient()

const organizationOptions = computed(() => props.organizations.map(o => ({ value: o.id, label: o.name })))

const triaging = ref<{ candidate: CandidateLead, mode: 'accept' | 'merge' } | null>(null)
const open = computed({
  get: () => triaging.value !== null,
  set: (value: boolean) => {
    if (!value) triaging.value = null
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

/** Défense en profondeur anti-XSS : n'accepter un lien d'annonce que s'il est http(s). */
function safeUrl(url?: string | null): string | null {
  return url && /^https?:\/\//i.test(url) ? url : null
}

function open_(candidate: CandidateLead, mode: 'accept' | 'merge'): void {
  form.languagePair = candidate.languagePair ?? 'en>fr'
  form.segment = 'PUBLISHING'
  form.priority = 'MEDIUM'
  if (mode === 'accept') {
    form.organizationName = candidate.organizationName ?? ''
    form.organizationType = 'PUBLISHER'
    form.website = safeUrl(candidate.url) ?? ''
  }
  else {
    form.organizationId = props.organizations[0]?.id ?? ''
  }
  triaging.value = { candidate, mode }
}
defineExpose({ open: open_ })

// Dédoublonnage suggéré : en mode « accepter », si le nom saisi ressemble à une organisation
// existante, on propose de la RÉUTILISER (bascule en fusion) plutôt que d'en créer un doublon.
const duplicateSuggestions = computed<Organization[]>(() =>
  triaging.value?.mode === 'accept'
    ? suggestDuplicateOrganizations(form.organizationName, props.organizations)
    : [],
)

/** L'utilisatrice choisit une organisation existante suggérée → on bascule sur la fusion. */
function useExistingOrganization(org: Organization): void {
  if (!triaging.value) return
  form.organizationId = org.id
  triaging.value = { candidate: triaging.value.candidate, mode: 'merge' }
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
    // La promotion crée une piste (+ éventuellement une organisation) : file de tri + tout ce qui
    // dépend d'une piste (kanban, aujourd'hui, dashboard) + liste des organisations.
    await Promise.all([
      invalidateLeadRelated(queryClient),
      queryClient.invalidateQueries({ queryKey: queryKeys.candidateQueue }),
      queryClient.invalidateQueries({ queryKey: queryKeys.organizations }),
    ])
    emit('promoted')
    toast.add({ title: t('sourcing.toasts.promoted'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: isConflict(error) ? t('sourcing.errors.conflict') : errorToastTitle(t, error), color: 'error' })
  }
  finally {
    submitting.value = false
  }
}
</script>

<template>
  <UModal
    v-model:open="open"
    :title="triaging?.mode === 'accept' ? t('sourcing.acceptTitle') : t('sourcing.mergeTitle')"
    :description="triaging?.mode === 'accept' ? t('sourcing.acceptDescription') : t('sourcing.mergeDescription')"
  >
    <template #body>
      <div v-if="triaging" class="flex flex-col gap-4">
        <template v-if="triaging.mode === 'accept'">
          <UFormField :label="t('sourcing.form.organizationName')" required>
            <UInput v-model="form.organizationName" class="w-full" maxlength="200" />
          </UFormField>
          <!-- Dédoublonnage suggéré : réutiliser une organisation existante au lieu d'un doublon. -->
          <UAlert
            v-if="duplicateSuggestions.length"
            color="warning"
            variant="subtle"
            icon="i-lucide-copy-check"
            :title="t('sourcing.duplicate.title')"
            :description="t('sourcing.duplicate.hint')"
          >
            <template #actions>
              <div class="flex flex-col gap-1.5 w-full">
                <UButton
                  v-for="org in duplicateSuggestions"
                  :key="org.id"
                  size="xs"
                  variant="soft"
                  color="warning"
                  icon="i-lucide-arrow-right"
                  class="self-start"
                  @click="useExistingOrganization(org)"
                >
                  {{ t('sourcing.duplicate.use', { name: org.name }) }}
                </UButton>
              </div>
            </template>
          </UAlert>
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
</template>
