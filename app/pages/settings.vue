<script setup lang="ts">
import type { Profile } from '~/types/leads'
import type { Mailbox } from '~/types/mailbox'
import type { AlertFeed } from '~/types/sourcing'

const { t, locale } = useI18n()
const profileApi = useProfile()
const mailboxApi = useMailbox()
const sourcingApi = useSourcing()
const toast = useToast()

const { data: profile, refresh, status } = await useAsyncData<Profile | null>(
  'profile-settings',
  () => profileApi.get(),
  { server: false, default: () => null },
)
const loading = computed(() => status.value === 'idle' || status.value === 'pending')

const weeklyGoal = ref(5)
const bio = ref('')
const specialties = ref('')
const signature = ref('')

watch(profile, (value) => {
  if (!value) return
  weeklyGoal.value = value.weeklyGoal
  bio.value = value.bio ?? ''
  specialties.value = value.specialties ?? ''
  signature.value = value.signature ?? ''
}, { immediate: true })

// ----- Boîte email (M2.1) -----
const { data: mailbox, refresh: refreshMailbox, status: mailboxStatus } = await useAsyncData<Mailbox | null>(
  'mailbox',
  () => mailboxApi.get(),
  { server: false, default: () => null },
)
const mailboxLoading = computed(() => mailboxStatus.value === 'idle' || mailboxStatus.value === 'pending')
const connecting = ref(false)
const confirmRevoke = ref(false)

async function connectMailbox(provider: 'GMAIL' | 'OUTLOOK'): Promise<void> {
  connecting.value = true
  try {
    // Redirection plein écran vers le consentement du fournisseur.
    window.location.href = await mailboxApi.startOAuth(provider)
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
    connecting.value = false
  }
}

const fetchingReplies = ref(false)

async function fetchRepliesNow(): Promise<void> {
  fetchingReplies.value = true
  try {
    await mailboxApi.fetchReplies()
    await refreshMailbox()
    toast.add({ title: t('mailbox.toasts.fetched'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    fetchingReplies.value = false
  }
}

async function revokeMailbox(): Promise<void> {
  try {
    await mailboxApi.revoke()
    await refreshMailbox()
    toast.add({ title: t('mailbox.toasts.revoked'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' })
}

// ----- Sources d'annonces (M3.1b) -----
const { data: feeds, refresh: refreshFeeds, status: feedsStatus } = await useAsyncData<AlertFeed[]>(
  'alert-feeds',
  () => sourcingApi.feeds(),
  { server: false, default: () => [] },
)
const feedsLoading = computed(() => feedsStatus.value === 'idle' || feedsStatus.value === 'pending')
const newFeedUrl = ref('')
const newFeedLabel = ref('')
const addingFeed = ref(false)
const feedUrlValid = computed(() => /^https?:\/\/.+/i.test(newFeedUrl.value.trim()))

async function addFeed(): Promise<void> {
  if (!feedUrlValid.value) return
  addingFeed.value = true
  try {
    await sourcingApi.addFeed({ source: 'RSS', url: newFeedUrl.value.trim(), label: newFeedLabel.value.trim() || null })
    newFeedUrl.value = ''
    newFeedLabel.value = ''
    await refreshFeeds()
    toast.add({ title: t('sourcing.feeds.added'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    addingFeed.value = false
  }
}

async function toggleFeed(feed: AlertFeed): Promise<void> {
  try {
    await sourcingApi.setFeedActive(feed.id, !feed.active)
    await refreshFeeds()
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}

async function removeFeed(feed: AlertFeed): Promise<void> {
  try {
    await sourcingApi.removeFeed(feed.id)
    await refreshFeeds()
    toast.add({ title: t('sourcing.feeds.removed'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}

const saving = ref(false)
/** v-model.number émet '' quand le champ est vidé : on n'envoie jamais un PATCH invalide. */
const goalValid = computed(() => Number.isInteger(weeklyGoal.value) && weeklyGoal.value >= 1 && weeklyGoal.value <= 99)

async function save(): Promise<void> {
  saving.value = true
  try {
    await profileApi.update({
      weeklyGoal: weeklyGoal.value,
      bio: bio.value.trim() || null,
      specialties: specialties.value.trim() || null,
      signature: signature.value.trim() || null,
    })
    await refresh()
    toast.add({ title: t('settings.toasts.saved'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <PageContainer width="atelier">
    <PageHeader :eyebrow="t('settings.eyebrow')" :title="t('settings.title')" />

    <div v-if="loading" role="status" class="mt-6 flex flex-col gap-4 max-w-2xl">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <USkeleton class="h-24 rounded-xl" />
      <USkeleton class="h-72 rounded-xl" />
    </div>

    <form v-else class="mt-6 flex flex-col gap-8 max-w-2xl" @submit.prevent="save">
      <!-- Objectif hebdomadaire -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-sm font-semibold">{{ t('settings.goal.title') }}</p>
        <UFormField :label="t('settings.goal.label')" :hint="t('settings.goal.hint')" class="mt-3">
          <UInput v-model.number="weeklyGoal" type="number" min="1" max="99" class="w-32" />
        </UFormField>
      </section>

      <!-- Présentation (matière première de la rédaction assistée) -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-4">
        <div>
          <p class="text-sm font-semibold">{{ t('settings.presentation.title') }}</p>
          <p class="text-xs text-muted mt-1">{{ t('settings.presentation.intro') }}</p>
        </div>
        <UFormField :label="t('settings.presentation.bioLabel')" :hint="t('settings.presentation.bioHint')">
          <UTextarea v-model="bio" :rows="4" autoresize class="w-full" maxlength="2000" />
        </UFormField>
        <UFormField :label="t('settings.presentation.specialtiesLabel')" :hint="t('settings.presentation.specialtiesHint')">
          <UTextarea v-model="specialties" :rows="3" autoresize class="w-full" maxlength="1000" />
        </UFormField>
        <UFormField :label="t('settings.presentation.signatureLabel')" :hint="t('settings.presentation.signatureHint')">
          <UTextarea v-model="signature" :rows="3" autoresize class="w-full" maxlength="500" />
        </UFormField>
      </section>

      <div class="flex justify-end">
        <UButton type="submit" :loading="saving" :disabled="!goalValid">{{ t('actions.save') }}</UButton>
      </div>
    </form>

    <!-- Boîte email connectée (M2.1) — hors du form profil : cycle de vie séparé. -->
    <section class="mt-8 border border-default rounded-xl p-4 bg-elevated/40 max-w-2xl">
      <div class="flex items-center gap-2 flex-wrap">
        <p class="text-sm font-semibold">{{ t('mailbox.title') }}</p>
        <UBadge
          v-if="mailbox"
          :color="mailbox.status === 'CONNECTED' ? 'success' : mailbox.status === 'ERROR' ? 'error' : 'neutral'"
          variant="soft"
          size="sm"
        >
          {{ t(`mailbox.statuses.${mailbox.status}`) }}
        </UBadge>
      </div>
      <p class="text-xs text-muted mt-1">{{ t('mailbox.intro') }}</p>

      <div v-if="mailboxLoading" class="mt-3 text-sm text-dimmed">{{ t('common.loading') }}</div>

      <template v-else-if="mailbox && (mailbox.status === 'CONNECTED' || mailbox.status === 'ERROR')">
        <div class="mt-3 flex items-center gap-3 flex-wrap text-sm">
          <UIcon name="i-lucide-mail-check" class="text-primary shrink-0" aria-hidden="true" />
          <span class="font-medium">{{ mailbox.emailAddress }}</span>
          <UBadge color="neutral" variant="soft" size="sm">{{ mailbox.provider }}</UBadge>
          <span v-if="mailbox.connectedAt" class="text-xs text-dimmed">
            {{ t('mailbox.connectedSince', { date: formatDate(mailbox.connectedAt) }) }}
          </span>
        </div>
        <UAlert
          v-if="mailbox.status === 'ERROR'"
          class="mt-3"
          color="error"
          variant="soft"
          icon="i-lucide-alert-triangle"
          :title="t(`mailbox.failures.${mailbox.failureReason ?? 'sync_failed'}`, t('mailbox.failures.sync_failed'))"
        >
          <template #actions>
            <UButton
              size="xs"
              variant="soft"
              color="error"
              :loading="connecting"
              @click="() => connectMailbox((mailbox?.provider as 'GMAIL' | 'OUTLOOK') ?? 'GMAIL')"
            >
              {{ t('mailbox.reconnect') }}
            </UButton>
          </template>
        </UAlert>
        <div class="mt-3 flex items-center gap-2 flex-wrap">
          <span v-if="mailbox.lastSyncAt" class="text-xs text-dimmed">
            {{ t('mailbox.lastSync', { date: formatDate(mailbox.lastSyncAt) }) }}
          </span>
          <div class="ml-auto flex gap-2">
            <UButton
              size="xs"
              variant="outline"
              icon="i-lucide-refresh-cw"
              :loading="fetchingReplies"
              @click="fetchRepliesNow"
            >
              {{ t('mailbox.fetchNow') }}
            </UButton>
            <UButton size="xs" variant="ghost" color="error" icon="i-lucide-unlink" @click="() => { confirmRevoke = true }">
              {{ t('mailbox.revoke') }}
            </UButton>
          </div>
        </div>
      </template>

      <div v-else class="mt-3 flex gap-2 flex-wrap">
        <UButton icon="i-lucide-mail-plus" :loading="connecting" @click="() => connectMailbox('GMAIL')">
          {{ t('mailbox.connectGmail') }}
        </UButton>
        <UButton variant="outline" icon="i-lucide-mail-plus" :loading="connecting" @click="() => connectMailbox('OUTLOOK')">
          {{ t('mailbox.connectOutlook') }}
        </UButton>
      </div>

      <ConfirmDialog
        v-model:open="confirmRevoke"
        :title="t('mailbox.confirmRevokeTitle')"
        :description="t('mailbox.confirmRevokeBody')"
        :confirm-label="t('mailbox.revoke')"
        danger
        @confirm="revokeMailbox"
      />
    </section>

    <!-- Sources d'annonces (M3.1b) : flux RSS relevés par « À trier ». -->
    <section class="mt-8 border border-default rounded-xl p-4 bg-elevated/40 max-w-2xl">
      <p class="text-sm font-semibold">{{ t('sourcing.feeds.title') }}</p>
      <p class="text-xs text-muted mt-1">{{ t('sourcing.feeds.intro') }}</p>

      <div v-if="feedsLoading" class="mt-3 text-sm text-dimmed">{{ t('common.loading') }}</div>

      <ul v-else-if="feeds.length" class="mt-3 flex flex-col gap-2">
        <li
          v-for="feed in feeds"
          :key="feed.id"
          class="flex items-center gap-3 flex-wrap border border-default rounded-lg p-3 bg-default"
        >
          <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
              <span class="font-medium text-sm truncate">{{ feed.label }}</span>
              <UBadge :color="feed.active ? 'success' : 'neutral'" variant="soft" size="sm">
                {{ feed.active ? t('sourcing.feeds.active') : t('sourcing.feeds.inactive') }}
              </UBadge>
            </div>
            <p class="text-xs text-dimmed truncate">{{ feed.url }}</p>
          </div>
          <div class="flex gap-2 shrink-0">
            <UButton size="xs" variant="outline" @click="() => toggleFeed(feed)">
              {{ feed.active ? t('sourcing.feeds.deactivate') : t('sourcing.feeds.activate') }}
            </UButton>
            <UButton
              size="xs"
              variant="ghost"
              color="error"
              icon="i-lucide-trash-2"
              :aria-label="t('sourcing.feeds.remove')"
              @click="() => removeFeed(feed)"
            />
          </div>
        </li>
      </ul>
      <p v-else class="mt-3 text-sm text-muted">{{ t('sourcing.feeds.empty') }}</p>

      <form class="mt-4 flex flex-col gap-3" @submit.prevent="addFeed">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <UFormField :label="t('sourcing.feeds.urlLabel')">
            <UInput v-model="newFeedUrl" class="w-full" type="url" placeholder="https://…/rss" />
          </UFormField>
          <UFormField :label="t('sourcing.feeds.labelLabel')" :hint="t('sourcing.feeds.labelHint')">
            <UInput v-model="newFeedLabel" class="w-full" maxlength="120" />
          </UFormField>
        </div>
        <div class="flex justify-end">
          <UButton type="submit" icon="i-lucide-plus" :loading="addingFeed" :disabled="!feedUrlValid">
            {{ t('sourcing.feeds.add') }}
          </UButton>
        </div>
      </form>
    </section>
  </PageContainer>
</template>
