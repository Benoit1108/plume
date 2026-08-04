<script setup lang="ts">
import type { AdminAccount } from '~/types/domain/admin'

/**
 * Back-office — table des comptes : recherche/filtre/tri/export + actions (reset 2FA, suppression RGPD
 * support avec confirmation). Émet `select` au clic sur un email (le parent ouvre la fiche détaillée).
 */
const emit = defineEmits<{ select: [account: AdminAccount] }>()

const { t, locale } = useI18n()
const adminApi = useAdmin()
const toast = useToast()
const queryClient = useQueryClient()

const search = ref('')
const debouncedSearch = useDebounced(search, 300)
const statusFilter = ref('all')
const sortBy = ref('email')
const statusOptions = computed(() => [
  { value: 'all', label: t('admin.accounts.filterAll') },
  { value: 'verified', label: t('admin.accounts.filterVerified') },
  { value: 'unverified', label: t('admin.accounts.filterUnverified') },
  { value: 'deleting', label: t('admin.accounts.filterDeleting') },
])
const sortOptions = computed(() => [
  { value: 'email', label: t('admin.accounts.sortEmail') },
  { value: 'leads', label: t('admin.accounts.sortLeads') },
  { value: 'created', label: t('admin.accounts.sortCreated') },
])
const { data: accountsData, isPending: accountsLoading } = useQuery({
  queryKey: computed(() => [...queryKeys.adminAccounts, debouncedSearch.value, statusFilter.value, sortBy.value] as const),
  queryFn: () => adminApi.accounts(debouncedSearch.value, statusFilter.value, sortBy.value),
})
const accounts = computed<AdminAccount[]>(() => accountsData.value ?? [])

const exporting = ref(false)
async function exportAccounts(): Promise<void> {
  if (exporting.value) return
  exporting.value = true
  try {
    const blob = await adminApi.accountsExport(debouncedSearch.value, statusFilter.value, sortBy.value)
    downloadBlob(blob, `plume-comptes-${new Date().toISOString().slice(0, 10)}.csv`)
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    exporting.value = false
  }
}

// Suppression RGPD côté support — confirmation obligatoire (action grave).
const target = ref<AdminAccount | null>(null)
const confirmOpen = ref(false)
const deleting = ref(false)

function askDeletion(account: AdminAccount): void {
  target.value = account
  confirmOpen.value = true
}

async function confirmDeletion(): Promise<void> {
  if (!target.value || deleting.value) return
  deleting.value = true
  try {
    await adminApi.requestDeletion(target.value.tenantId)
    toast.add({ title: t('admin.accounts.deletionRequested'), color: 'success' })
    await queryClient.invalidateQueries({ queryKey: queryKeys.adminAccounts })
    await queryClient.invalidateQueries({ queryKey: queryKeys.adminOverview })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
  finally {
    deleting.value = false
    target.value = null
  }
}

async function resetTwoFactor(account: AdminAccount): Promise<void> {
  try {
    await adminApi.resetTwoFactor(account.tenantId)
    toast.add({ title: t('admin.accounts.twoFactorReset'), color: 'success' })
  }
  catch {
    toast.add({ title: t('common.error'), color: 'error' })
  }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' })
}
</script>

<template>
  <section class="mt-8" :aria-label="t('admin.accounts.title')">
    <div class="flex items-center gap-3 flex-wrap">
      <h2 class="text-sm font-semibold">{{ t('admin.accounts.title') }}</h2>
      <div class="ml-auto flex items-center gap-2 flex-wrap">
        <UInput
          v-model="search"
          icon="i-lucide-search"
          :placeholder="t('admin.accounts.search')"
          :aria-label="t('admin.accounts.search')"
          size="sm"
          class="w-52"
        />
        <USelect v-model="statusFilter" :items="statusOptions" value-key="value" size="sm" :aria-label="t('admin.accounts.status')" class="w-40" />
        <USelect v-model="sortBy" :items="sortOptions" value-key="value" size="sm" :aria-label="t('admin.accounts.sortLabel')" class="w-40" />
        <UButton size="sm" color="neutral" variant="outline" icon="i-lucide-download" :loading="exporting" @click="exportAccounts">
          {{ t('admin.accounts.export') }}
        </UButton>
      </div>
    </div>

    <div v-if="accountsLoading" role="status" class="mt-3">
      <span class="sr-only">{{ t('common.loading') }}</span>
      <USkeleton class="h-32 rounded-xl" />
    </div>

    <div v-else class="mt-3 border border-default rounded-xl overflow-x-auto">
      <table class="w-full text-sm">
        <thead>
          <tr class="border-b border-default text-left text-xs text-muted">
            <th class="px-3 py-2 font-medium">{{ t('admin.accounts.email') }}</th>
            <th class="px-3 py-2 font-medium">{{ t('admin.accounts.status') }}</th>
            <th class="px-3 py-2 font-medium text-right">{{ t('admin.accounts.orgs') }}</th>
            <th class="px-3 py-2 font-medium text-right">{{ t('admin.accounts.leads') }}</th>
            <th class="px-3 py-2 font-medium">{{ t('admin.accounts.mailbox') }}</th>
            <th class="px-3 py-2"><span class="sr-only">{{ t('admin.accounts.actions') }}</span></th>
          </tr>
        </thead>
        <tbody>
          <tr v-if="accounts.length === 0">
            <td colspan="6" class="px-3 py-6 text-center text-muted">{{ t('admin.accounts.empty') }}</td>
          </tr>
          <tr v-for="account in accounts" :key="account.tenantId" class="border-b border-default last:border-0">
            <td class="px-3 py-2 font-medium">
              <button type="button" class="text-left hover:text-primary hover:underline underline-offset-2" @click="emit('select', account)">
                {{ account.email }}
              </button>
            </td>
            <td class="px-3 py-2">
              <UBadge v-if="account.deletionRequestedAt" color="error" variant="soft" size="sm">
                {{ t('admin.accounts.deleting', { date: formatDate(account.deletionRequestedAt) }) }}
              </UBadge>
              <UBadge v-else-if="!account.emailVerified" color="warning" variant="soft" size="sm">
                {{ t('admin.accounts.unverified') }}
              </UBadge>
              <UBadge v-else color="success" variant="soft" size="sm">
                {{ t('admin.accounts.active') }}
              </UBadge>
            </td>
            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ account.organizations }}</td>
            <td class="px-3 py-2 text-right font-mono tabular-nums">{{ account.leads }}</td>
            <td class="px-3 py-2 text-muted">{{ t(`admin.accounts.mailboxStatus.${account.mailboxStatus}`, account.mailboxStatus) }}</td>
            <td class="px-3 py-2 text-right whitespace-nowrap">
              <UButton
                size="xs"
                variant="ghost"
                icon="i-lucide-shield-off"
                :aria-label="t('admin.accounts.resetTwoFactor', { email: account.email })"
                @click="resetTwoFactor(account)"
              />
              <UButton
                v-if="!account.deletionRequestedAt"
                size="xs"
                color="error"
                variant="ghost"
                icon="i-lucide-trash-2"
                :aria-label="t('admin.accounts.requestDeletion', { email: account.email })"
                @click="askDeletion(account)"
              />
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <ConfirmDialog
      v-model:open="confirmOpen"
      :title="t('admin.accounts.confirmTitle')"
      :description="t('admin.accounts.confirmBody', { email: target?.email ?? '' })"
      :confirm-label="t('admin.accounts.confirmAction')"
      danger
      @confirm="confirmDeletion"
    />
  </section>
</template>
