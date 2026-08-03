<script setup lang="ts">
import type { Subscription } from '~/types/billing'
import type { Profile } from '~/types/leads'
import type { Mailbox } from '~/types/mailbox'
import type { AlertFeed } from '~/types/sourcing'

const { t, locale } = useI18n()
const profileApi = useProfile()
const mailboxApi = useMailbox()
const sourcingApi = useSourcing()
const toast = useToast()

const queryClient = useQueryClient()
const { data: profileData, isPending: loading } = useQuery({ queryKey: queryKeys.profile, queryFn: () => profileApi.get() })
const profile = computed<Profile | null>(() => profileData.value ?? null)
async function refresh(): Promise<void> { await queryClient.invalidateQueries({ queryKey: queryKeys.profile }) }

// ----- Abonnement (V2.2) -----
const billing = useBilling()
const { data: subData } = useQuery({ queryKey: queryKeys.billingSubscription, queryFn: () => billing.subscription() })
const subscription = computed<Subscription | null>(() => subData.value ?? null)
const trialDaysLeft = computed<number>(() => {
  const end = subscription.value?.trialEndsAt
  return end ? Math.max(0, Math.ceil((new Date(end).getTime() - Date.now()) / 86_400_000)) : 0
})
const billingBusy = ref(false)
async function subscribe(plan: 'monthly' | 'annual'): Promise<void> {
  if (billingBusy.value) return
  billingBusy.value = true
  try {
    const { url } = await billing.checkout(plan)
    window.location.href = url // redirection vers Stripe (ou retour app en factice)
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
    billingBusy.value = false
  }
}
async function manageBilling(): Promise<void> {
  if (billingBusy.value) return
  billingBusy.value = true
  try {
    const { url } = await billing.portal()
    window.location.href = url
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
    billingBusy.value = false
  }
}

const weeklyGoal = ref(5)
const bio = ref('')
const specialties = ref('')
const signature = ref('')
const digestFrequency = ref<'NONE' | 'DAILY' | 'WEEKLY'>('DAILY')
/** Saisie libre « 7, 21, 45 » ; parsée en jours à l'enregistrement. */
const cadenceInput = ref('')
/** Libellés d'étapes personnalisés (ADR-0031) : un champ par statut, vide = libellé par défaut. */
const pipelineLabels = ref<Record<string, string>>({})
/** Préférences fines de notification : matrice type × canal (défaut = tout activé). */
const NOTIFICATION_TYPES = ['reply_received', 'followup_due', 'candidate_to_triage', 'email_send_failed', 'mailbox_disconnected'] as const
const notificationPrefs = ref<Record<string, { inApp: boolean, email: boolean }>>({})

watch(profile, (value) => {
  if (!value) return
  weeklyGoal.value = value.weeklyGoal
  bio.value = value.bio ?? ''
  specialties.value = value.specialties ?? ''
  signature.value = value.signature ?? ''
  digestFrequency.value = value.digestFrequency
  cadenceInput.value = value.followUpCadence.join(', ')
  pipelineLabels.value = Object.fromEntries(LEAD_STATUSES.map(s => [s, value.pipelineLabels[s] ?? '']))
  // Défaut = tout activé : on fusionne les coupures stockées avec les valeurs par défaut (vraies).
  notificationPrefs.value = Object.fromEntries(NOTIFICATION_TYPES.map(type => [type, {
    inApp: value.notificationPreferences[type]?.inApp ?? true,
    email: value.notificationPreferences[type]?.email ?? true,
  }]))
}, { immediate: true })

/** « 7, 21, 45 » → [7, 21, 45] (entiers positifs uniquement ; vide = pas de relance auto). */
const parsedCadence = computed<number[]>(() =>
  cadenceInput.value
    .split(/[\s,]+/)
    .map(part => Number.parseInt(part, 10))
    .filter(n => Number.isInteger(n) && n >= 1 && n <= 365),
)

const digestOptions = computed(() => [
  { value: 'DAILY', label: t('settings.digest.daily') },
  { value: 'WEEKLY', label: t('settings.digest.weekly') },
  { value: 'NONE', label: t('settings.digest.none') },
])

// ----- Boîte email (M2.1) -----
const { data: mailboxData, isPending: mailboxLoading } = useQuery({ queryKey: queryKeys.mailbox, queryFn: () => mailboxApi.get() })
const mailbox = computed<Mailbox | null>(() => mailboxData.value ?? null)
async function refreshMailbox(): Promise<void> { await queryClient.invalidateQueries({ queryKey: queryKeys.mailbox }) }
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

const fetchingAlerts = ref(false)

async function fetchAlertsNow(): Promise<void> {
  fetchingAlerts.value = true
  try {
    await mailboxApi.fetchAlerts()
    await refreshMailbox()
    toast.add({ title: t('mailbox.toasts.alertsFetched'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
  finally {
    fetchingAlerts.value = false
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
const { data: feedsData, isPending: feedsLoading } = useQuery({ queryKey: queryKeys.feeds, queryFn: () => sourcingApi.feeds() })
const feeds = computed<AlertFeed[]>(() => feedsData.value ?? [])
async function refreshFeeds(): Promise<void> { await queryClient.invalidateQueries({ queryKey: queryKeys.feeds }) }
const newFeedUrl = ref('')
const newFeedLabel = ref('')
const addingFeed = ref(false)
const feedUrlValid = computed(() => /^https?:\/\/.+/i.test(newFeedUrl.value.trim()))
// Focus ramené en tête de section après un retrait (l'élément traité disparaît).
const sourcesRef = ref<HTMLElement | null>(null)
function focusSources(): void {
  void nextTick(() => sourcesRef.value?.focus())
}

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
    toast.add({ title: isConflict(error) ? t('sourcing.feeds.errorConflict') : errorToastTitle(t, error), color: 'error' })
  }
  finally {
    addingFeed.value = false
  }
}

async function toggleFeed(feed: AlertFeed): Promise<void> {
  try {
    await sourcingApi.setFeedActive(feed.id, !feed.active)
    await refreshFeeds()
    toast.add({ title: feed.active ? t('sourcing.feeds.deactivated') : t('sourcing.feeds.activated'), color: 'success' })
  }
  catch (error) {
    toast.add({ title: errorToastTitle(t, error), color: 'error' })
  }
}

// Retrait d'un flux = action destructive → confirmation, puis focus en tête de section.
const feedToRemove = ref<AlertFeed | null>(null)
const confirmRemoveFeed = computed({
  get: () => feedToRemove.value !== null,
  set: (open: boolean) => {
    if (!open) feedToRemove.value = null
  },
})

async function doRemoveFeed(): Promise<void> {
  const feed = feedToRemove.value
  if (!feed) return
  try {
    await sourcingApi.removeFeed(feed.id)
    feedToRemove.value = null
    await refreshFeeds()
    focusSources()
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
      digestFrequency: digestFrequency.value,
      followUpCadence: parsedCadence.value,
      pipelineLabels: Object.fromEntries(Object.entries(pipelineLabels.value).filter(([, v]) => v.trim() !== '')),
      notificationPreferences: notificationPrefs.value,
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

    <!-- Abonnement (V2.2) : état + s'abonner / gérer -->
    <section v-if="subscription && subscription.status !== 'none'" class="mt-6 border border-default rounded-xl p-4 bg-elevated/40 max-w-2xl">
      <div class="flex items-center gap-2 flex-wrap">
        <h2 class="text-sm font-semibold">{{ t('settings.billing.title') }}</h2>
        <UBadge :color="subscription.entitled ? 'success' : 'error'" variant="soft" size="sm">
          {{ t(`settings.billing.status.${subscription.status}`) }}
        </UBadge>
      </div>
      <p class="text-xs text-muted mt-1">
        <span v-if="subscription.status === 'trialing' && subscription.entitled">{{ t('settings.billing.trialLeft', { days: trialDaysLeft }, trialDaysLeft) }}</span>
        <span v-else-if="!subscription.entitled">{{ t('settings.billing.readOnly') }}</span>
        <span v-else>{{ t('settings.billing.activeHint') }}</span>
      </p>
      <div class="mt-3 flex items-center gap-2 flex-wrap">
        <UButton v-if="subscription.canManage" size="sm" variant="soft" :loading="billingBusy" @click="manageBilling">
          {{ t('settings.billing.manage') }}
        </UButton>
        <template v-else>
          <UButton size="sm" :loading="billingBusy" @click="subscribe('monthly')">{{ t('settings.billing.subscribeMonthly') }}</UButton>
          <UButton size="sm" variant="soft" :loading="billingBusy" @click="subscribe('annual')">{{ t('settings.billing.subscribeAnnual') }}</UButton>
        </template>
      </div>
    </section>

    <div v-if="loading" role="status" class="mt-6 flex flex-col gap-4 max-w-2xl">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <USkeleton class="h-24 rounded-xl" />
      <USkeleton class="h-72 rounded-xl" />
    </div>

    <form v-else class="mt-6 flex flex-col gap-8 max-w-2xl" @submit.prevent="save">
      <!-- Objectif hebdomadaire -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40">
        <h2 class="text-sm font-semibold">{{ t('settings.goal.title') }}</h2>
        <UFormField :label="t('settings.goal.label')" :hint="t('settings.goal.hint')" class="mt-3">
          <UInput v-model.number="weeklyGoal" type="number" min="1" max="99" class="w-32" />
        </UFormField>
      </section>

      <!-- Séquence de relance -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40">
        <h2 class="text-sm font-semibold">{{ t('settings.cadence.title') }}</h2>
        <UFormField :label="t('settings.cadence.label')" :hint="t('settings.cadence.hint')" class="mt-3">
          <UInput v-model="cadenceInput" placeholder="7, 21, 45" class="w-64" />
        </UFormField>
        <p class="text-xs text-muted mt-2">
          {{ parsedCadence.length === 0 ? t('settings.cadence.none') : t('settings.cadence.preview', { days: parsedCadence.join(' · J+') }) }}
        </p>
      </section>

      <!-- Digest email des notifications -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40">
        <h2 class="text-sm font-semibold">{{ t('settings.digest.title') }}</h2>
        <UFormField :label="t('settings.digest.label')" :hint="t('settings.digest.hint')" class="mt-3">
          <USelect v-model="digestFrequency" :items="digestOptions" value-key="value" class="w-56" />
        </UFormField>
      </section>

      <!-- Préférences fines de notification : matrice type × canal (in-app / email), défaut = tout activé -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40">
        <h2 class="text-sm font-semibold">{{ t('settings.notifications.title') }}</h2>
        <p class="text-xs text-muted mt-1">{{ t('settings.notifications.hint') }}</p>
        <div class="mt-3 overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-xs text-dimmed uppercase tracking-wide">
                <th scope="col" class="py-1.5 pr-3 font-semibold">{{ t('settings.notifications.type') }}</th>
                <th scope="col" class="py-1.5 px-3 font-semibold text-center">{{ t('settings.notifications.inApp') }}</th>
                <th scope="col" class="py-1.5 pl-3 font-semibold text-center">{{ t('settings.notifications.email') }}</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-[var(--ui-border)]">
              <tr v-for="type in NOTIFICATION_TYPES" :key="type">
                <td class="py-2 pr-3">{{ t(`settings.notifications.types.${type}`) }}</td>
                <td class="py-2 px-3 text-center">
                  <UCheckbox
                    v-if="notificationPrefs[type]"
                    v-model="notificationPrefs[type].inApp"
                    :aria-label="t('settings.notifications.inAppFor', { type: t(`settings.notifications.types.${type}`) })"
                  />
                </td>
                <td class="py-2 pl-3 text-center">
                  <UCheckbox
                    v-if="notificationPrefs[type]"
                    v-model="notificationPrefs[type].email"
                    :aria-label="t('settings.notifications.emailFor', { type: t(`settings.notifications.types.${type}`) })"
                  />
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Libellés d'étapes du pipeline (ADR-0031 : cosmétique, la logique ne change pas) -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40">
        <h2 class="text-sm font-semibold">{{ t('settings.pipeline.title') }}</h2>
        <p class="text-xs text-muted mt-1">{{ t('settings.pipeline.hint') }}</p>
        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3">
          <UFormField v-for="status in LEAD_STATUSES" :key="status" :label="t(`pipeline.statuses.${status}`)">
            <UInput v-model="pipelineLabels[status]" :placeholder="t(`pipeline.statuses.${status}`)" maxlength="40" class="w-full" />
          </UFormField>
        </div>
      </section>

      <!-- Présentation (matière première de la rédaction assistée) -->
      <section class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-4">
        <div>
          <h2 class="text-sm font-semibold">{{ t('settings.presentation.title') }}</h2>
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
        <h2 class="text-sm font-semibold">{{ t('mailbox.title') }}</h2>
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

      <div v-if="mailboxLoading" role="status" class="mt-3 text-sm text-dimmed">
        <span class="sr-only">{{ t('common.loading') }}</span>
        {{ t('common.loading') }}
      </div>

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
            <UButton
              size="xs"
              variant="outline"
              icon="i-lucide-download"
              :loading="fetchingAlerts"
              @click="fetchAlertsNow"
            >
              {{ t('mailbox.fetchAlertsNow') }}
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
      <h2 ref="sourcesRef" tabindex="-1" class="text-sm font-semibold outline-none">{{ t('sourcing.feeds.title') }}</h2>
      <p class="text-xs text-muted mt-1">{{ t('sourcing.feeds.intro') }}</p>

      <div v-if="feedsLoading" role="status" class="mt-3 text-sm text-dimmed">
        <span class="sr-only">{{ t('common.loading') }}</span>
        {{ t('common.loading') }}
      </div>

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
              @click="() => { feedToRemove = feed }"
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

      <ConfirmDialog
        v-model:open="confirmRemoveFeed"
        :title="t('sourcing.feeds.confirmRemoveTitle')"
        :description="t('sourcing.feeds.confirmRemoveBody', { label: feedToRemove?.label ?? '' })"
        :confirm-label="t('sourcing.feeds.remove')"
        danger
        @confirm="doRemoveFeed"
      />
    </section>
  </PageContainer>
</template>
