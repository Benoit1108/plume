<script setup lang="ts">
import type { Contact, ContactInput, Organization, OrganizationInput } from '~/types/directory'

const route = useRoute()
const id = route.params.id as string

const { t } = useI18n()
const { typeLabel, segmentLabel } = useDirectoryLabels()
const directory = useDirectory()
const toast = useToast()

const { data: org, refresh, status } = await useAsyncData<Organization | null>(
  `org-${id}`,
  () => directory.get(id),
  { server: false, default: () => null },
)

const editing = ref(false)
const savingOrg = ref(false)
const addingContact = ref(false)
const editingContactId = ref<string | null>(null)
const savingContact = ref(false)
const togglingDoNotContact = ref(false)

const confirmAllow = ref(false)
const contactToDelete = ref<Contact | null>(null)
const confirmDelete = computed({
  get: () => contactToDelete.value !== null,
  set: (open: boolean) => {
    if (!open) contactToDelete.value = null
  },
})

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

function initials(name: string): string {
  return name
    .split(/\s+/)
    .map(w => w.charAt(0))
    .filter(Boolean)
    .slice(0, 2)
    .join('')
    .toUpperCase()
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

async function addContact(payload: ContactInput): Promise<void> {
  savingContact.value = true
  try {
    await directory.addContact(id, payload)
    addingContact.value = false
    await refresh()
    toast.add({ title: t('directory.toasts.contactAdded'), color: 'success' })
  }
  catch {
    errorToast()
  }
  finally {
    savingContact.value = false
  }
}

async function saveContact(contactId: string, payload: ContactInput): Promise<void> {
  savingContact.value = true
  try {
    await directory.updateContact(id, contactId, payload)
    editingContactId.value = null
    await refresh()
    toast.add({ title: t('directory.toasts.contactUpdated'), color: 'success' })
  }
  catch {
    errorToast()
  }
  finally {
    savingContact.value = false
  }
}

async function deleteContact(): Promise<void> {
  const contact = contactToDelete.value
  if (!contact) return
  try {
    await directory.removeContact(id, contact.id)
    await refresh()
    toast.add({ title: t('directory.toasts.contactDeleted'), color: 'success' })
  }
  catch {
    errorToast()
  }
}
</script>

<template>
  <UContainer class="py-8 max-w-3xl">
    <UButton variant="link" icon="i-lucide-arrow-left" to="/organizations" class="px-0 mb-2">
      {{ t('directory.title') }}
    </UButton>

    <div v-if="status === 'pending'" class="text-dimmed py-12">{{ t('common.loading') }}</div>
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

      <section class="mt-10">
        <div class="flex items-center gap-2">
          <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold flex-1">{{ t('directory.detail.contacts') }}</p>
          <UButton v-if="!addingContact" size="sm" variant="outline" icon="i-lucide-plus" @click="() => { addingContact = true }">
            {{ t('directory.detail.addContact') }}
          </UButton>
        </div>

        <div v-if="addingContact" class="mt-4 border border-default rounded-lg p-4 bg-elevated/40">
          <ContactForm
            :submitting="savingContact"
            :submit-label="t('actions.add')"
            @submit="addContact"
            @cancel="addingContact = false"
          />
        </div>

        <div class="mt-4 border border-default rounded-lg divide-y divide-[var(--ui-border)]">
          <div v-for="c in org.contacts" :key="c.id" class="p-4">
            <div v-if="editingContactId !== c.id" class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-full bg-elevated grid place-items-center text-xs font-bold text-primary shrink-0" aria-hidden="true">
                {{ initials(c.fullName) }}
              </div>
              <div class="min-w-0">
                <div class="font-medium text-sm">{{ c.fullName }}</div>
                <div v-if="c.role" class="text-xs text-dimmed">{{ c.role }}</div>
              </div>
              <div class="ml-auto flex items-center gap-2">
                <span v-if="c.email" class="font-mono text-xs text-muted hidden sm:inline">{{ c.email }}</span>
                <LangStamp v-if="c.preferredLanguage" :code="c.preferredLanguage" />
                <UButton size="xs" variant="ghost" icon="i-lucide-pencil" :aria-label="t('actions.edit')" @click="() => { editingContactId = c.id }" />
                <UButton size="xs" variant="ghost" color="error" icon="i-lucide-trash-2" :aria-label="t('actions.delete')" @click="() => { contactToDelete = c }" />
              </div>
            </div>
            <ContactForm
              v-else
              :initial="c"
              :submitting="savingContact"
              :submit-label="t('actions.save')"
              @submit="(p: ContactInput) => saveContact(c.id, p)"
              @cancel="editingContactId = null"
            />
          </div>

          <div v-if="!org.contacts.length" class="p-6 text-center text-muted text-sm">
            {{ t('directory.detail.noContacts') }}
          </div>
        </div>
      </section>

      <!-- Confirmations : suppression (destructive) et réautorisation (RGPD). -->
      <ConfirmDialog
        v-model:open="confirmDelete"
        :title="t('directory.detail.deleteContactTitle')"
        :description="t('directory.detail.deleteContactBody', { name: contactToDelete?.fullName ?? '' })"
        :confirm-label="t('actions.delete')"
        danger
        @confirm="deleteContact"
      />
      <ConfirmDialog
        v-model:open="confirmAllow"
        :title="t('directory.detail.allowContactTitle')"
        :description="t('directory.detail.allowContactBody')"
        :confirm-label="t('directory.doNotContact.clear')"
        @confirm="() => applyDoNotContact(false)"
      />
    </template>
  </UContainer>
</template>
