<script setup lang="ts">
import type { ContactInput, Organization, OrganizationInput } from '~/types/directory'

const route = useRoute()
const id = route.params.id as string
const directory = useDirectory()

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

const typeLabels: Record<string, string> = {
  PUBLISHER: 'Éditeur',
  AV_STUDIO: 'Labo A/V',
  AGENCY: 'Agence',
  OTHER: 'Autre',
}
const segmentLabels: Record<string, string> = {
  PUBLISHING: 'Édition',
  AUDIOVISUAL: 'Audiovisuel',
  TECHNICAL: 'Technique',
  OTHER: 'Autre',
}

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

async function saveOrg(payload: OrganizationInput): Promise<void> {
  savingOrg.value = true
  try {
    await directory.update(id, payload)
    editing.value = false
    await refresh()
  }
  finally {
    savingOrg.value = false
  }
}

async function toggleDoNotContact(): Promise<void> {
  await directory.update(id, { doNotContact: !org.value!.doNotContact })
  await refresh()
}

async function addContact(payload: ContactInput): Promise<void> {
  savingContact.value = true
  try {
    await directory.addContact(id, payload)
    addingContact.value = false
    await refresh()
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
  }
  finally {
    savingContact.value = false
  }
}

async function deleteContact(contactId: string): Promise<void> {
  await directory.removeContact(id, contactId)
  await refresh()
}
</script>

<template>
  <UContainer class="py-8 max-w-3xl">
    <UButton variant="link" icon="i-lucide-arrow-left" to="/organizations" class="px-0 mb-2">Répertoire</UButton>

    <div v-if="status === 'pending'" class="text-dimmed py-12">Chargement…</div>
    <div v-else-if="!org" class="text-muted py-12">Organisation introuvable.</div>

    <template v-else>
      <div v-if="!editing" class="flex flex-col sm:flex-row sm:items-start gap-4">
        <div class="min-w-0">
          <div class="flex items-center gap-2 flex-wrap">
            <h1 class="font-serif text-3xl font-semibold">{{ org.name }}</h1>
            <UBadge color="neutral" variant="soft">{{ typeLabels[org.type] ?? org.type }}</UBadge>
            <span v-if="org.doNotContact" class="inline-flex items-center gap-1 text-error text-xs font-medium">
              <UIcon name="i-lucide-flag" /> Ne pas contacter
            </span>
          </div>

          <div class="mt-2 flex gap-2 items-center flex-wrap text-sm text-muted">
            <a v-if="org.website" :href="org.website" target="_blank" rel="noopener" class="hover:text-primary underline underline-offset-2">
              {{ org.website }}
            </a>
            <span v-if="org.country" class="font-mono text-xs uppercase">{{ org.country }}</span>
            <span class="flex gap-1">
              <LangStamp v-for="l in org.workingLanguages" :key="l" :code="l" />
            </span>
          </div>

          <div v-if="org.segments.length" class="mt-3 flex gap-1.5 flex-wrap">
            <UBadge v-for="s in org.segments" :key="s" color="neutral" variant="soft" size="sm">
              {{ segmentLabels[s] ?? s }}
            </UBadge>
          </div>

          <p v-if="org.notes" class="mt-4 text-sm text-muted whitespace-pre-line">{{ org.notes }}</p>
        </div>

        <div class="flex gap-2 shrink-0 flex-wrap sm:ml-auto">
          <UButton color="neutral" variant="outline" size="sm" @click="toggleDoNotContact">
            {{ org.doNotContact ? 'Réautoriser' : 'Ne pas contacter' }}
          </UButton>
          <UButton size="sm" icon="i-lucide-pencil" @click="() => { editing = true }">Modifier</UButton>
        </div>
      </div>

      <div v-else>
        <h2 class="font-serif text-2xl font-semibold mb-4">Modifier l'organisation</h2>
        <OrgForm
          :initial="orgInitial()"
          :submitting="savingOrg"
          submit-label="Enregistrer"
          @submit="saveOrg"
          @cancel="editing = false"
        />
      </div>

      <section class="mt-10">
        <div class="flex items-center gap-2">
          <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold flex-1">Contacts</p>
          <UButton v-if="!addingContact" size="sm" variant="outline" icon="i-lucide-plus" @click="() => { addingContact = true }">
            Contact
          </UButton>
        </div>

        <div v-if="addingContact" class="mt-4 border border-default rounded-lg p-4 bg-elevated/40">
          <ContactForm
            :submitting="savingContact"
            submit-label="Ajouter"
            @submit="addContact"
            @cancel="addingContact = false"
          />
        </div>

        <div class="mt-4 border border-default rounded-lg divide-y divide-[var(--ui-border)]">
          <div v-for="c in org.contacts" :key="c.id" class="p-4">
            <div v-if="editingContactId !== c.id" class="flex items-center gap-3">
              <div class="w-9 h-9 rounded-full bg-elevated grid place-items-center text-xs font-bold text-primary shrink-0">
                {{ initials(c.fullName) }}
              </div>
              <div class="min-w-0">
                <div class="font-medium text-sm">{{ c.fullName }}</div>
                <div v-if="c.role" class="text-xs text-dimmed">{{ c.role }}</div>
              </div>
              <div class="ml-auto flex items-center gap-2">
                <span v-if="c.email" class="font-mono text-xs text-muted hidden sm:inline">{{ c.email }}</span>
                <LangStamp v-if="c.preferredLanguage" :code="c.preferredLanguage" />
                <UButton size="xs" variant="ghost" icon="i-lucide-pencil" aria-label="Modifier" @click="() => { editingContactId = c.id ?? null }" />
                <UButton size="xs" variant="ghost" color="error" icon="i-lucide-trash-2" aria-label="Supprimer" @click="deleteContact(c.id as string)" />
              </div>
            </div>
            <ContactForm
              v-else
              :initial="c"
              :submitting="savingContact"
              submit-label="Enregistrer"
              @submit="(p: ContactInput) => saveContact(c.id as string, p)"
              @cancel="editingContactId = null"
            />
          </div>

          <div v-if="!org.contacts.length" class="p-6 text-center text-muted text-sm">
            Aucun contact pour l'instant.
          </div>
        </div>
      </section>
    </template>
  </UContainer>
</template>
