<script setup lang="ts">
import type { Contact, ContactInput } from '~/types/domain/directory'

/**
 * Répertoire — liste des contacts d'une organisation + CRUD (ajout / édition inline / suppression).
 * Auto-suffisant : les mutations invalident la fiche + la liste + les pistes (hasReachableContact).
 */
const props = defineProps<{ orgId: string, contacts: Contact[] }>()

const { t } = useI18n()
const directory = useDirectory()
const toast = useToast()
const queryClient = useQueryClient()

const addingContact = ref(false)
const editingContactId = ref<string | null>(null)
const savingContact = ref(false)
const contactToDelete = ref<Contact | null>(null)
const confirmDelete = computed({
  get: () => contactToDelete.value !== null,
  set: (open: boolean) => {
    if (!open) contactToDelete.value = null
  },
})

async function refresh(): Promise<void> {
  await Promise.all([
    queryClient.invalidateQueries({ queryKey: queryKeys.organization(props.orgId) }),
    queryClient.invalidateQueries({ queryKey: queryKeys.organizations }),
    queryClient.invalidateQueries({ queryKey: queryKeys.leads }),
  ])
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

async function addContact(payload: ContactInput): Promise<void> {
  savingContact.value = true
  try {
    await directory.addContact(props.orgId, payload)
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
    await directory.updateContact(props.orgId, contactId, payload)
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
    await directory.removeContact(props.orgId, contact.id)
    await refresh()
    toast.add({ title: t('directory.toasts.contactDeleted'), color: 'success' })
  }
  catch {
    errorToast()
  }
}
</script>

<template>
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
      <div v-for="c in contacts" :key="c.id" class="p-4">
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

      <div v-if="!contacts.length" class="p-6 text-center text-muted text-sm">
        {{ t('directory.detail.noContacts') }}
      </div>
    </div>

    <ConfirmDialog
      v-model:open="confirmDelete"
      :title="t('directory.detail.deleteContactTitle')"
      :description="t('directory.detail.deleteContactBody', { name: contactToDelete?.fullName ?? '' })"
      :confirm-label="t('actions.delete')"
      danger
      @confirm="deleteContact"
    />
  </section>
</template>
