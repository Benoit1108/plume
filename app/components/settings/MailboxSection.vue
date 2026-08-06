<script setup lang="ts">
import type { Mailbox } from '~/types/domain/mailbox'

/** Réglages — boîte email connectée (M2.1) : connexion OAuth, relèves manuelles, révocation. */
const { t, locale } = useI18n()
const mailboxApi = useMailbox()
const toast = useToast()
const queryClient = useQueryClient()

const { data: mailboxData, isPending: mailboxLoading, isError: mailboxFailed, refetch: refetchMailbox } = useQuery({ queryKey: queryKeys.mailbox, queryFn: () => mailboxApi.get() })
const mailbox = computed<Mailbox | null>(() => mailboxData.value ?? null)
async function refreshMailbox(): Promise<void> { await queryClient.invalidateQueries({ queryKey: queryKeys.mailbox }) }

const connecting = ref(false)
const confirmRevoke = ref(false)
const fetchingReplies = ref(false)
const fetchingAlerts = ref(false)

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
</script>

<template>
  <section class="border border-default rounded-xl p-4 bg-elevated/40 max-w-2xl">
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

    <!-- Une panne de chargement n'est pas « aucune boîte connectée » : l'utilisatrice croirait sa
         connexion perdue et la referait (revue UX-P2a). -->
    <QueryError v-else-if="mailboxFailed" class="mt-3" @retry="() => { void refetchMailbox() }" />

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
</template>
