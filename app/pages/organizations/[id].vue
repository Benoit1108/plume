<script setup lang="ts">
import type { Organization, OrganizationInput } from '~/types/domain/directory'

const route = useRoute()
const id = route.params.id as string

const { t } = useI18n()
const { typeLabel, segmentLabel } = useDirectoryLabels()
const directory = useDirectory()
const toast = useToast()

const queryClient = useQueryClient()
const { data: orgData, isPending: loading, isError, refetch } = useQuery({ queryKey: queryKeys.organization(id), queryFn: () => directory.get(id) })
const org = computed<Organization | null>(() => orgData.value ?? null)

// Une mutation du répertoire touche la fiche, la liste ET les pistes (hasReachableContact).
async function refresh(): Promise<void> {
  await Promise.all([
    queryClient.invalidateQueries({ queryKey: queryKeys.organization(id) }),
    queryClient.invalidateQueries({ queryKey: queryKeys.organizations }),
    queryClient.invalidateQueries({ queryKey: queryKeys.leads }),
  ])
}

const editing = ref(false)
const savingOrg = ref(false)
const togglingDoNotContact = ref(false)
const confirmAllow = ref(false)

/** Lien externe rendu seulement si l'URL est http(s) — jamais de javascript:. */
const safeWebsite = computed(() => {
  const url = org.value?.website
  return url && /^https?:\/\//i.test(url) ? url : null
})

function orgInitial() {
  const o = org.value!
  return {
    name: o.name,
    type: o.type,
    website: o.website ?? '',
    country: o.country ?? '',
    workingLanguages: o.workingLanguages.join(' '),
    segments: o.segments,
    notes: o.notes ?? '',
  }
}

function errorToast(): void {
  toast.add({ title: t('common.error'), color: 'error' })
}

async function saveOrg(payload: OrganizationInput): Promise<void> {
  savingOrg.value = true
  try {
    await directory.update(id, payload)
    editing.value = false
    await refresh()
    toast.add({ title: t('directory.toasts.updated'), color: 'success' })
  }
  catch {
    errorToast()
  }
  finally {
    savingOrg.value = false
  }
}

function onToggleDoNotContact(): void {
  if (org.value?.doNotContact) {
    // Réautoriser = décision RGPD sensible -> confirmation explicite.
    confirmAllow.value = true
  }
  else {
    void applyDoNotContact(true)
  }
}

async function applyDoNotContact(flag: boolean): Promise<void> {
  togglingDoNotContact.value = true
  try {
    await directory.update(id, { doNotContact: flag })
    await refresh()
    toast.add({ title: flag ? t('directory.toasts.marked') : t('directory.toasts.cleared'), color: 'success' })
  }
  catch {
    errorToast()
  }
  finally {
    togglingDoNotContact.value = false
  }
}

</script>

<template>
  <PageContainer width="atelier">
    <UButton variant="link" icon="i-lucide-arrow-left" to="/organizations" class="px-0 mb-2">
      {{ t('directory.title') }}
    </UButton>

    <div v-if="loading" role="status" class="flex flex-col gap-4">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <USkeleton class="h-9 w-64 rounded" />
      <USkeleton class="h-32 rounded-xl" />
    </div>
    <!-- Distinguer « n'existe pas » d'« a échoué » (revue UX-P2a). -->
    <QueryError v-else-if="isError" @retry="() => { void refetch() }" />

    <div v-else-if="!org" class="text-muted py-12">{{ t('directory.detail.notFound') }}</div>

    <template v-else>
      <div v-if="!editing" class="flex flex-col sm:flex-row sm:items-start gap-4">
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="font-serif text-3xl font-semibold">{{ org.name }}</h1>
            <UBadge color="neutral" variant="soft">{{ typeLabel(org.type) }}</UBadge>
            <span v-if="org.doNotContact" class="inline-flex items-center gap-1 text-error text-xs font-medium">
              <UIcon name="i-lucide-flag" aria-hidden="true" /> {{ t('directory.doNotContact.flag') }}
            </span>
          </div>

          <div class="mt-2 flex gap-2 items-center flex-wrap text-sm text-muted">
            <a v-if="safeWebsite" :href="safeWebsite" target="_blank" rel="noopener" class="hover:text-primary underline underline-offset-2">
              {{ safeWebsite }}
            </a>
            <span v-if="org.country" class="font-mono text-xs uppercase">{{ org.country }}</span>
            <span class="flex gap-1">
              <LangStamp v-for="l in org.workingLanguages" :key="l" :code="l" />
            </span>
          </div>

          <div v-if="org.segments.length" class="mt-3 flex gap-1.5 flex-wrap">
            <UBadge v-for="s in org.segments" :key="s" color="neutral" variant="soft" size="sm">
              {{ segmentLabel(s) }}
            </UBadge>
          </div>

          <p v-if="org.notes" class="mt-4 text-sm text-muted whitespace-pre-line">{{ org.notes }}</p>
        </div>

        <div class="flex gap-2 shrink-0 flex-wrap sm:ml-auto">
          <UButton
            v-if="!org.doNotContact"
            size="sm"
            variant="outline"
            icon="i-lucide-kanban"
            :to="`/leads/new?organizationId=${org.id}`"
          >
            {{ t('pipeline.createFromOrg') }}
          </UButton>
          <UButton color="neutral" variant="outline" size="sm" :loading="togglingDoNotContact" @click="onToggleDoNotContact">
            {{ org.doNotContact ? t('directory.doNotContact.clear') : t('directory.doNotContact.mark') }}
          </UButton>
          <UButton size="sm" icon="i-lucide-pencil" @click="() => { editing = true }">{{ t('actions.edit') }}</UButton>
        </div>
      </div>

      <div v-else>
        <h2 class="font-serif text-2xl font-semibold mb-4">{{ t('directory.detail.editTitle') }}</h2>
        <OrgForm
          :initial="orgInitial()"
          :submitting="savingOrg"
          :submit-label="t('actions.save')"
          @submit="saveOrg"
          @cancel="editing = false"
        />
      </div>

      <OrgContactList :org-id="id" :contacts="org.contacts" />

      <!-- Réautorisation (RGPD) : décision sensible → confirmation explicite. -->
      <ConfirmDialog
        v-model:open="confirmAllow"
        :title="t('directory.detail.allowContactTitle')"
        :description="t('directory.detail.allowContactBody')"
        :confirm-label="t('directory.doNotContact.clear')"
        @confirm="() => applyDoNotContact(false)"
      />
    </template>
  </PageContainer>
</template>
