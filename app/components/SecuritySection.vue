<script setup lang="ts">
/** Page Compte — sécurité : 2FA TOTP (enrôlement en 2 temps, codes de secours) + sessions actives. */
const { t, locale } = useI18n()
const accountApi = useAccount()
const toast = useToast()
const queryClient = useQueryClient()

const { data: twoFactor } = useQuery({ queryKey: queryKeys.twoFactor, queryFn: () => accountApi.twoFactorStatus() })
const { data: sessionsData } = useQuery({ queryKey: queryKeys.sessions, queryFn: () => accountApi.sessions() })
const sessions = computed(() => sessionsData.value?.sessions ?? [])

async function refresh(): Promise<void> {
  await queryClient.invalidateQueries({ queryKey: queryKeys.twoFactor })
  await queryClient.invalidateQueries({ queryKey: queryKeys.sessions })
}

// --- Enrôlement 2FA (setup → code → codes de secours) ---
const enrollment = ref<{ secret: string, otpauthUri: string } | null>(null)
const confirmCode = ref('')
const backupCodes = ref<string[]>([])
const busy = ref(false)

async function startSetup(): Promise<void> {
  if (busy.value) return
  busy.value = true
  try {
    enrollment.value = await accountApi.twoFactorSetup()
    confirmCode.value = ''
  }
  catch { toast.add({ title: t('common.error'), color: 'error' }) }
  finally { busy.value = false }
}

async function confirmSetup(): Promise<void> {
  if (busy.value || confirmCode.value.length < 6) return
  busy.value = true
  try {
    const res = await accountApi.twoFactorConfirm(confirmCode.value)
    backupCodes.value = res.backupCodes
    enrollment.value = null
    await refresh()
    toast.add({ title: t('account.twoFactor.enabled'), color: 'success' })
  }
  catch { toast.add({ title: t('account.twoFactor.invalidCode'), color: 'error' }) }
  finally { busy.value = false }
}

async function copyBackupCodes(): Promise<void> {
  await navigator.clipboard.writeText(backupCodes.value.join('\n'))
  toast.add({ title: t('account.twoFactor.copied'), color: 'success' })
}

// --- Désactivation (mot de passe exigé) ---
const disablePassword = ref('')
async function disable(): Promise<void> {
  if (busy.value || disablePassword.value === '') return
  busy.value = true
  try {
    await accountApi.twoFactorDisable(disablePassword.value)
    disablePassword.value = ''
    backupCodes.value = []
    await refresh()
    toast.add({ title: t('account.twoFactor.disabled'), color: 'success' })
  }
  catch { toast.add({ title: t('account.errors.invalidCurrent'), color: 'error' }) }
  finally { busy.value = false }
}

// --- Sessions ---
async function revoke(id: number): Promise<void> {
  await accountApi.revokeSession(id)
  await refresh()
}
async function revokeOthers(): Promise<void> {
  await accountApi.revokeOtherSessions()
  await refresh()
  toast.add({ title: t('account.sessions.othersRevoked'), color: 'success' })
}

function formatDate(iso: string | null): string {
  return iso ? new Date(iso).toLocaleString(locale.value, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }) : '—'
}
</script>

<template>
  <!-- 2FA -->
  <section class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-4">
    <div>
      <p class="text-sm font-semibold">{{ t('account.twoFactor.title') }}</p>
      <p class="text-xs text-muted mt-1">{{ t('account.twoFactor.intro') }}</p>
    </div>

    <!-- Codes de secours fraîchement générés : affichés UNE fois -->
    <UAlert v-if="backupCodes.length" color="warning" variant="subtle" :title="t('account.twoFactor.backupTitle')" :description="t('account.twoFactor.backupIntro')">
      <template #actions>
        <div class="flex flex-col gap-2 w-full">
          <code class="text-xs whitespace-pre-line font-mono">{{ backupCodes.join('\n') }}</code>
          <UButton size="xs" variant="soft" icon="i-lucide-copy" class="self-start" @click="copyBackupCodes">
            {{ t('actions.copy') }}
          </UButton>
        </div>
      </template>
    </UAlert>

    <template v-if="twoFactor?.enabled">
      <div class="flex items-center gap-2">
        <UBadge color="success" variant="soft">{{ t('account.twoFactor.active') }}</UBadge>
        <span class="text-xs text-muted">{{ t('account.twoFactor.backupRemaining', { count: twoFactor.remainingBackupCodes }) }}</span>
      </div>
      <form class="flex items-end gap-3 flex-wrap" @submit.prevent="disable">
        <UFormField :label="t('account.twoFactor.disablePassword')">
          <UInput v-model="disablePassword" type="password" autocomplete="current-password" />
        </UFormField>
        <UButton type="submit" color="error" variant="soft" :loading="busy" :disabled="disablePassword === ''">
          {{ t('account.twoFactor.disable') }}
        </UButton>
      </form>
    </template>

    <template v-else-if="enrollment">
      <ol class="text-sm flex flex-col gap-2 list-decimal list-inside">
        <li>{{ t('account.twoFactor.step1') }}</li>
        <li class="flex flex-col gap-1">
          <span>{{ t('account.twoFactor.step2') }}</span>
          <code class="font-mono text-xs bg-elevated rounded p-2 break-all select-all">{{ enrollment.secret }}</code>
        </li>
        <li>{{ t('account.twoFactor.step3') }}</li>
      </ol>
      <form class="flex items-end gap-3" @submit.prevent="confirmSetup">
        <UFormField :label="t('account.twoFactor.codeLabel')">
          <UInput v-model="confirmCode" inputmode="numeric" autocomplete="one-time-code" maxlength="6" />
        </UFormField>
        <UButton type="submit" :loading="busy" :disabled="confirmCode.length < 6">
          {{ t('account.twoFactor.activate') }}
        </UButton>
      </form>
    </template>

    <div v-else class="flex justify-end">
      <UButton variant="soft" icon="i-lucide-shield-check" :loading="busy" @click="startSetup">
        {{ t('account.twoFactor.enable') }}
      </UButton>
    </div>
  </section>

  <!-- Sessions actives -->
  <section class="border border-default rounded-xl p-4 bg-elevated/40 flex flex-col gap-3">
    <div class="flex items-center justify-between gap-3">
      <div>
        <p class="text-sm font-semibold">{{ t('account.sessions.title') }}</p>
        <p class="text-xs text-muted mt-1">{{ t('account.sessions.intro') }}</p>
      </div>
      <UButton v-if="sessions.length > 1" size="xs" variant="soft" color="error" @click="revokeOthers">
        {{ t('account.sessions.revokeOthers') }}
      </UButton>
    </div>
    <ul class="flex flex-col gap-2">
      <li v-for="session in sessions" :key="session.id" class="flex items-center gap-3 text-sm">
        <UIcon name="i-lucide-monitor-smartphone" class="size-4 text-muted" aria-hidden="true" />
        <span>{{ t('account.sessions.expires', { date: formatDate(session.expiresAt) }) }}</span>
        <UBadge v-if="session.current" color="primary" variant="soft" size="sm">{{ t('account.sessions.current') }}</UBadge>
        <UButton
          v-else
          size="xs"
          variant="ghost"
          color="error"
          class="ml-auto"
          :aria-label="t('account.sessions.revoke')"
          icon="i-lucide-x"
          @click="revoke(session.id)"
        />
      </li>
    </ul>
  </section>
</template>
