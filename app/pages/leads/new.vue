<script setup lang="ts">
import type { Organization, Segment } from '~/types/directory'
import type { LeadInput, LeadPriority, LeadSource } from '~/types/leads'

const { t } = useI18n()
const { segmentOptions } = useDirectoryLabels()
const { priorityOptions, sourceOptions } = useLeadLabels()
const directory = useDirectory()
const leads = useLeads()
const toast = useToast()
const route = useRoute()

const { data: organizations } = await useAsyncData<Organization[]>(
  'organizations-for-lead',
  () => directory.list(),
  { server: false, default: () => [] },
)

const NO_CONTACT = 'NONE'
const form = reactive({
  organizationId: typeof route.query.organizationId === 'string' ? route.query.organizationId : '',
  contactId: NO_CONTACT,
  languagePair: 'en>fr',
  source: 'DIRECT' as LeadSource,
  priority: 'MEDIUM' as LeadPriority,
  segment: 'PUBLISHING' as Segment,
})

const organizationOptions = computed(() =>
  organizations.value.map(org => ({ value: org.id, label: org.name })),
)

const selectedOrganization = computed(() =>
  organizations.value.find(org => org.id === form.organizationId) ?? null,
)

const contactOptions = computed(() => [
  { value: NO_CONTACT, label: t('pipeline.new.noContact') },
  ...(selectedOrganization.value?.contacts ?? []).map(contact => ({ value: contact.id, label: contact.fullName })),
])

// Changer d'organisation invalide le contact choisi ; pré-remplit le segment.
watch(selectedOrganization, (org) => {
  form.contactId = NO_CONTACT
  const first = org?.segments[0]
  if (first) form.segment = first as Segment
})

const saving = ref(false)
const error = ref('')

async function onSubmit(): Promise<void> {
  error.value = ''
  saving.value = true
  try {
    const payload: LeadInput = {
      organizationId: form.organizationId,
      contactId: form.contactId !== NO_CONTACT ? form.contactId : null,
      languagePair: form.languagePair.trim().toLowerCase(),
      source: form.source,
      priority: form.priority,
      segment: form.segment,
    }
    const lead = await leads.create(payload)
    toast.add({ title: t('pipeline.toasts.created'), color: 'success' })
    await navigateTo(`/leads/${lead.id}`)
  }
  catch {
    error.value = t('pipeline.new.error')
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <PageContainer width="form">
    <PageHeader back-to="/leads" :back-label="t('pipeline.title')" :title="t('pipeline.new.title')" />

    <UAlert v-if="error" color="error" variant="subtle" :description="error" class="mb-4" />

    <form class="flex flex-col gap-4" @submit.prevent="onSubmit">
      <UFormField :label="t('pipeline.new.organization')" required>
        <USelect v-model="form.organizationId" :items="organizationOptions" value-key="value" label-key="label" class="w-full" required />
      </UFormField>

      <UFormField :label="t('pipeline.new.contact')">
        <USelect v-model="form.contactId" :items="contactOptions" value-key="value" label-key="label" class="w-full" :disabled="!selectedOrganization" />
      </UFormField>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <UFormField :label="t('pipeline.new.languagePair')" :hint="t('pipeline.new.languagePairHint')" required>
          <UInput v-model="form.languagePair" placeholder="en>fr" pattern="[a-zA-Z]{2}>[a-zA-Z]{2}" required class="w-full font-mono" />
        </UFormField>
        <UFormField :label="t('pipeline.new.segment')">
          <USelect v-model="form.segment" :items="segmentOptions" value-key="value" label-key="label" class="w-full" />
        </UFormField>
        <UFormField :label="t('pipeline.new.source')">
          <USelect v-model="form.source" :items="sourceOptions" value-key="value" label-key="label" class="w-full" />
        </UFormField>
        <UFormField :label="t('pipeline.new.priority')">
          <USelect v-model="form.priority" :items="priorityOptions" value-key="value" label-key="label" class="w-full" />
        </UFormField>
      </div>

      <div class="flex gap-2 pt-2">
        <UButton type="submit" :loading="saving" :disabled="!form.organizationId">{{ t('actions.create') }}</UButton>
        <UButton type="button" color="neutral" variant="ghost" @click="() => { void navigateTo('/leads') }">{{ t('actions.cancel') }}</UButton>
      </div>
    </form>
  </PageContainer>
</template>
