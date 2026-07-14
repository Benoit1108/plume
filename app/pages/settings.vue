<script setup lang="ts">
import type { Profile } from '~/types/leads'
import type { Mailbox } from '~/types/mailbox'

const { t, locale } = useI18n()
const profileApi = useProfile()
const mailboxApi = useMailbox()
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
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
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
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    saving.value = false
  }
}
</script>

<template>
  <UContainer class="py-8 max-w-2xl">
    <p class="text-[11px] uppercase tracking-widest text-dimmed font-semibold">{{ t('settings.eyebrow') }}</p>
    <h1 class="font-serif text-3xl font-semibold mt-1">{{ t('settings.title') }}</h1>

    <div v-if="loading" class="py-12 text-center text-dimmed">{{ t('common.loading') }}</div>

    <form v-else class="mt-6 flex flex-col gap-8" @submit.prevent="save">
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
    <section class="mt-8 border border-default rounded-xl p-4 bg-elevated/40">
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
  </UContainer>
</template>
