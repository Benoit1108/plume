<script setup lang="ts">
import type { AdminAccount } from '~/types/admin'

/**
 * Back-office (ROLE_ADMIN). L'entrée nav n'apparaît qu'aux admins ; l'autorité reste l'API
 * (403 sinon). Vue d'ensemble en comptages + comptes (recherche) + suppression RGPD support.
 */
const { t, locale } = useI18n()
const adminApi = useAdmin()
const toast = useToast()
const queryClient = useQueryClient()

const { data: overview, isError: overviewError } = useQuery({
  queryKey: queryKeys.adminOverview,
  queryFn: () => adminApi.overview(),
})

const search = ref('')
const debouncedSearch = useDebounced(search, 300)
const { data: accountsData, isPending: accountsLoading } = useQuery({
  queryKey: computed(() => [...queryKeys.adminAccounts, debouncedSearch.value] as const),
  queryFn: () => adminApi.accounts(debouncedSearch.value),
})
const accounts = computed<AdminAccount[]>(() => accountsData.value ?? [])

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

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' })
}

const failedDepth = computed(() => overview.value?.queues.failed ?? 0)
</script>

<template>
  <PageContainer width="atelier">
    <PageHeader :eyebrow="t('admin.eyebrow')" :title="t('admin.title')" />

    <UAlert v-if="overviewError" color="error" variant="subtle" class="mt-6" :description="t('common.error')" />

    <!-- Vue d'ensemble : comptages uniquement (jamais de contenu métier des traductrices). -->
    <section v-if="overview" class="mt-6 grid grid-cols-2 md:grid-cols-4 gap-3" :aria-label="t('admin.overview.title')">
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.overview.accounts') }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums">{{ overview.accounts.total }}</p>
        <p class="text-xs text-muted mt-1">
          {{ t('admin.overview.accountsDetail', { unverified: overview.accounts.unverified, deleting: overview.accounts.pendingDeletion }) }}
        </p>
      </div>
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.overview.activity') }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums">{{ overview.business.leads }}</p>
        <p class="text-xs text-muted mt-1">
          {{ t('admin.overview.activityDetail', { orgs: overview.business.organizations, sent: overview.business.messagesSent }) }}
        </p>
      </div>
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.overview.mailboxes') }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums">{{ overview.business.mailboxesConnected }}</p>
        <p class="text-xs mt-1" :class="overview.business.mailboxesError > 0 ? 'text-error' : 'text-muted'">
          {{ t('admin.overview.mailboxesError', { count: overview.business.mailboxesError }) }}
        </p>
      </div>
      <div class="border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.overview.queues') }}</p>
        <p class="text-2xl font-semibold font-mono tabular-nums" :class="failedDepth > 0 ? 'text-error' : ''">
          {{ failedDepth }}
        </p>
        <p class="text-xs text-muted mt-1">{{ t('admin.overview.queuesDetail') }}</p>
      </div>
    </section>

    <!-- Comptes -->
    <section class="mt-8" :aria-label="t('admin.accounts.title')">
      <div class="flex items-center gap-3 flex-wrap">
        <h2 class="text-sm font-semibold">{{ t('admin.accounts.title') }}</h2>
        <UInput
          v-model="search"
          icon="i-lucide-search"
          :placeholder="t('admin.accounts.search')"
          :aria-label="t('admin.accounts.search')"
          size="sm"
          class="ml-auto w-64"
        />
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
              <td class="px-3 py-2 font-medium">{{ account.email }}</td>
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
              <td class="px-3 py-2 text-muted">{{ account.mailboxStatus }}</td>
              <td class="px-3 py-2 text-right">
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
    </section>

    <ConfirmDialog
      v-model:open="confirmOpen"
      :title="t('admin.accounts.confirmTitle')"
      :description="t('admin.accounts.confirmBody', { email: target?.email ?? '' })"
      :confirm-label="t('admin.accounts.confirmAction')"
      danger
      @confirm="confirmDeletion"
    />
  </PageContainer>
</template>
