<script setup lang="ts">
import type { AdminAccount, AdminAccountDetail, AdminAlerts, AdminAuditEntry, AdminBilling, AdminTrends } from '~/types/admin'

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

const { data: status } = useQuery({ queryKey: queryKeys.adminStatus, queryFn: () => adminApi.status() })
const { data: metrics } = useQuery({ queryKey: queryKeys.adminMetrics, queryFn: () => adminApi.metrics() })
/** Bornes des barres d'inscriptions (hauteur relative au pic). */
const signupPeak = computed(() => Math.max(1, ...(metrics.value?.signups ?? []).map(s => s.count)))
function shortWeek(week: string): string {
  return new Date(week).toLocaleDateString(locale.value, { day: 'numeric', month: 'short' })
}
const systemHealthy = computed(() =>
  status.value !== undefined
  && status.value.failed === 0
  && status.value.mailboxesError === 0
  && status.value.backlogAgeSeconds < 600,
)
function formatTokens(n: number): string {
  return new Intl.NumberFormat(locale.value).format(n)
}

function backlogLabel(seconds: number): string {
  if (seconds < 60) return t('admin.status.backlogFresh')
  if (seconds < 3600) return t('admin.status.backlogMinutes', { count: Math.round(seconds / 60) })
  return t('admin.status.backlogHours', { count: Math.round(seconds / 3600) })
}

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

// Fiche compte détaillée : ouverte au clic sur un email (chargée à la demande).
const detailId = ref<string | null>(null)
const detailOpen = computed({
  get: () => detailId.value !== null,
  set: (open: boolean) => { if (!open) detailId.value = null },
})
const { data: detailData, isPending: detailLoading } = useQuery({
  queryKey: computed(() => queryKeys.adminAccount(detailId.value ?? '')),
  queryFn: () => adminApi.accountDetail(detailId.value as string),
  enabled: computed(() => detailId.value !== null),
})
const detail = computed<AdminAccountDetail | null>(() => detailData.value ?? null)
function openDetail(account: AdminAccount): void {
  detailId.value = account.tenantId
}

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

// Santé & alertes : la liste « à regarder ».
const { data: alertsData } = useQuery({ queryKey: queryKeys.adminAlerts, queryFn: () => adminApi.alerts() })
const alerts = computed<AdminAlerts | null>(() => alertsData.value ?? null)
const alertsCount = computed(() => {
  const a = alerts.value
  return a ? a.inactiveAccounts.length + a.mailboxesInError.length + a.stuckVerification.length : 0
})

// Croissance & acquisition : actifs par semaine + entonnoir.
const { data: trendsData } = useQuery({ queryKey: queryKeys.adminTrends, queryFn: () => adminApi.trends() })
const trends = computed<AdminTrends | null>(() => trendsData.value ?? null)
const activeMax = computed(() => Math.max(1, ...(trends.value?.weeklyActive ?? []).map(w => w.count)))
const funnelStages = computed(() => {
  const f = trends.value?.funnel
  return f
    ? ([
        { key: 'signedUp', count: f.signedUp },
        { key: 'verified', count: f.verified },
        { key: 'activated', count: f.activated },
        { key: 'active30d', count: f.active30d },
      ] as const)
    : []
})
function funnelPercent(count: number): number {
  const total = trends.value?.funnel.signedUp ?? 0
  return total > 0 ? Math.round((count / total) * 100) : 0
}
function weekLabel(iso: string): string {
  return new Date(`${iso}T00:00:00`).toLocaleDateString(locale.value, { day: 'numeric', month: 'short' })
}

// Billing : abonnés par statut + revenu mensuel estimé.
const { data: billingData } = useQuery({ queryKey: queryKeys.adminBilling, queryFn: () => adminApi.billing() })
const billing = computed<AdminBilling | null>(() => billingData.value ?? null)
const euroFormat = computed(() => new Intl.NumberFormat(locale.value, { style: 'currency', currency: 'EUR', maximumFractionDigits: 0 }))

// Accès offert (comped) : bascule depuis la fiche compte.
const compBusy = ref(false)
async function toggleComp(comped: boolean): Promise<void> {
  if (compBusy.value || !detailId.value) return
  compBusy.value = true
  try {
    await adminApi.setComp(detailId.value, comped)
    await queryClient.invalidateQueries({ queryKey: queryKeys.adminAccount(detailId.value ?? '') })
    await queryClient.invalidateQueries({ queryKey: queryKeys.adminBilling })
    toast.add({ title: comped ? t('admin.detail.compGranted') : t('admin.detail.compRevoked'), color: 'success' })
  }
  catch { toast.add({ title: t('common.error'), color: 'error' }) }
  finally { compBusy.value = false }
}

// Journal d'audit (hors tenant) : les 100 dernières actions sensibles.
const { data: auditData } = useQuery({ queryKey: queryKeys.adminAudit, queryFn: () => adminApi.audit() })
const auditEntries = computed<AdminAuditEntry[]>(() => auditData.value ?? [])
function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString(locale.value, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
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

    <!-- Statut système (opérationnel) -->
    <section v-if="status" class="mt-8" :aria-label="t('admin.status.title')">
      <div class="flex items-center gap-3">
        <h2 class="text-sm font-semibold">{{ t('admin.status.title') }}</h2>
        <UBadge :color="systemHealthy ? 'success' : 'warning'" variant="soft">
          {{ systemHealthy ? t('admin.status.healthy') : t('admin.status.attention') }}
        </UBadge>
      </div>
      <div class="mt-3 grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t('admin.status.failed') }}</p>
          <p class="text-2xl font-semibold font-mono tabular-nums" :class="status.failed > 0 ? 'text-error' : ''">{{ status.failed }}</p>
        </div>
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t('admin.status.backlog') }}</p>
          <p class="text-lg font-semibold mt-1.5">{{ backlogLabel(status.backlogAgeSeconds) }}</p>
        </div>
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t('admin.status.mailboxesError') }}</p>
          <p class="text-2xl font-semibold font-mono tabular-nums" :class="status.mailboxesError > 0 ? 'text-error' : ''">{{ status.mailboxesError }}</p>
        </div>
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t('admin.status.db') }}</p>
          <p class="text-lg font-semibold mt-1.5">{{ status.db === 'ok' ? t('admin.status.dbOk') : status.db }}</p>
        </div>
      </div>

      <!-- Garde-fou coût IA : consommation du mois vs plafond + coupe-circuit -->
      <div class="mt-3 border border-default rounded-xl p-4 bg-elevated/40 flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-xs text-muted">{{ t('admin.status.aiUsage') }}</p>
          <p class="text-lg font-semibold mt-1.5 font-mono tabular-nums">
            {{ formatTokens(status.aiUsage.periodTokens) }}
            <span class="text-muted text-sm font-normal">
              / {{ status.aiUsage.monthlyTokenBudget > 0 ? formatTokens(status.aiUsage.monthlyTokenBudget) : t('admin.status.aiUnlimited') }}
              {{ t('admin.status.aiTokens') }}
            </span>
          </p>
          <p class="text-xs text-muted mt-0.5">{{ t('admin.status.aiCalls', { count: status.aiUsage.calls }, status.aiUsage.calls) }}</p>
        </div>
        <UBadge :color="status.aiUsage.enabled ? 'success' : 'error'" variant="soft">
          {{ status.aiUsage.enabled ? t('admin.status.aiEnabled') : t('admin.status.aiDisabled') }}
        </UBadge>
      </div>
    </section>

    <!-- Santé & alertes : la liste « à regarder » -->
    <section v-if="alerts" class="mt-8" :aria-label="t('admin.alerts.title')">
      <div class="flex items-center gap-2">
        <h2 class="text-sm font-semibold">{{ t('admin.alerts.title') }}</h2>
        <UBadge :color="alertsCount > 0 ? 'warning' : 'success'" variant="soft" size="sm">
          {{ alertsCount > 0 ? t('admin.alerts.count', { count: alertsCount }, alertsCount) : t('admin.alerts.allClear') }}
        </UBadge>
      </div>
      <div v-if="alertsCount > 0" class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-3">
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs font-semibold">{{ t('admin.alerts.inactive') }} ({{ alerts.inactiveAccounts.length }})</p>
          <p class="text-xs text-muted mt-0.5">{{ t('admin.alerts.inactiveHint') }}</p>
          <ul class="mt-2 flex flex-col gap-1 text-sm">
            <li v-for="a in alerts.inactiveAccounts" :key="a.email" class="truncate">{{ a.email }}</li>
            <li v-if="alerts.inactiveAccounts.length === 0" class="text-muted text-xs">{{ t('admin.alerts.none') }}</li>
          </ul>
        </div>
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs font-semibold">{{ t('admin.alerts.mailboxError') }} ({{ alerts.mailboxesInError.length }})</p>
          <p class="text-xs text-muted mt-0.5">{{ t('admin.alerts.mailboxErrorHint') }}</p>
          <ul class="mt-2 flex flex-col gap-1 text-sm">
            <li v-for="a in alerts.mailboxesInError" :key="a.email" class="truncate">{{ a.email }}</li>
            <li v-if="alerts.mailboxesInError.length === 0" class="text-muted text-xs">{{ t('admin.alerts.none') }}</li>
          </ul>
        </div>
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs font-semibold">{{ t('admin.alerts.stuckVerification') }} ({{ alerts.stuckVerification.length }})</p>
          <p class="text-xs text-muted mt-0.5">{{ t('admin.alerts.stuckVerificationHint') }}</p>
          <ul class="mt-2 flex flex-col gap-1 text-sm">
            <li v-for="a in alerts.stuckVerification" :key="a.email" class="truncate">{{ a.email }}</li>
            <li v-if="alerts.stuckVerification.length === 0" class="text-muted text-xs">{{ t('admin.alerts.none') }}</li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Métriques produit -->
    <section v-if="metrics" class="mt-8" :aria-label="t('admin.metrics.title')">
      <h2 class="text-sm font-semibold">{{ t('admin.metrics.title') }}</h2>
      <div class="mt-3 grid grid-cols-2 md:grid-cols-3 gap-3">
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t('admin.metrics.total') }}</p>
          <p class="text-2xl font-semibold font-mono tabular-nums">{{ metrics.accounts.total }}</p>
          <p class="text-xs text-muted mt-1">{{ t('admin.metrics.verified', { count: metrics.accounts.verified }) }}</p>
        </div>
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t('admin.metrics.active') }}</p>
          <p class="text-2xl font-semibold font-mono tabular-nums">{{ metrics.accounts.active30d }}</p>
          <p class="text-xs text-muted mt-1">{{ t('admin.metrics.activeHint') }}</p>
        </div>
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t('admin.metrics.messagesSent') }}</p>
          <p class="text-2xl font-semibold font-mono tabular-nums">{{ metrics.totals.messagesSent }}</p>
          <p class="text-xs text-muted mt-1">{{ t('admin.metrics.totalsHint', { orgs: metrics.totals.organizations, leads: metrics.totals.leads }) }}</p>
        </div>
      </div>

      <!-- Inscriptions (8 dernières semaines) -->
      <div class="mt-3 border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.metrics.signups') }}</p>
        <p v-if="metrics.signups.length === 0" class="text-sm text-muted mt-2">{{ t('admin.metrics.signupsEmpty') }}</p>
        <ol v-else class="mt-3 flex items-end gap-2 h-20">
          <li v-for="week in metrics.signups" :key="week.week" class="flex flex-col items-center gap-1 flex-1">
            <span class="font-mono tabular-nums text-xs">{{ week.count }}</span>
            <div class="w-full bg-primary/60 rounded-t-sm min-h-0.5" :style="{ height: `${Math.round((week.count / signupPeak) * 100)}%` }" role="img" :aria-label="`${shortWeek(week.week)} : ${week.count}`" />
            <span class="text-[10px] text-dimmed whitespace-nowrap">{{ shortWeek(week.week) }}</span>
          </li>
        </ol>
      </div>

      <!-- Répartition des pistes par statut -->
      <div class="mt-3 border border-default rounded-xl p-4 bg-elevated/40">
        <p class="text-xs text-muted">{{ t('admin.metrics.leadsByStatus') }}</p>
        <div class="mt-2 flex flex-wrap gap-2">
          <UBadge v-for="(count, leadStatus) in metrics.leadsByStatus" :key="leadStatus" color="neutral" variant="soft">
            {{ t(`pipeline.statuses.${leadStatus}`) }} · {{ count }}
          </UBadge>
        </div>
      </div>
    </section>

    <!-- Croissance & acquisition : actifs dans le temps + entonnoir -->
    <section v-if="trends" class="mt-8" :aria-label="t('admin.trends.title')">
      <h2 class="text-sm font-semibold">{{ t('admin.trends.title') }}</h2>
      <div class="mt-3 grid grid-cols-1 lg:grid-cols-2 gap-3">
        <!-- Courbe des comptes actifs par semaine -->
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t('admin.trends.activeWeekly') }}</p>
          <ol class="mt-4 grid grid-cols-12 gap-1 items-end">
            <li v-for="week in trends.weeklyActive" :key="week.week" class="flex flex-col items-center gap-1">
              <div class="h-20 w-full flex items-end">
                <div
                  class="w-full rounded-t-sm bg-primary/70 min-h-0.5"
                  :style="{ height: `${(week.count / activeMax) * 100}%` }"
                  role="img"
                  :aria-label="`${weekLabel(week.week)} : ${week.count}`"
                />
              </div>
              <span class="font-mono tabular-nums text-[10px]">{{ week.count }}</span>
            </li>
          </ol>
          <p class="text-[10px] text-dimmed mt-1">{{ t('admin.trends.activeWeeklyHint') }}</p>
        </div>
        <!-- Entonnoir d'acquisition -->
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t('admin.trends.funnel') }}</p>
          <ul class="mt-3 flex flex-col gap-2">
            <li v-for="stage in funnelStages" :key="stage.key">
              <div class="flex items-baseline justify-between text-sm">
                <span>{{ t(`admin.trends.stages.${stage.key}`) }}</span>
                <span class="font-mono tabular-nums text-xs text-muted">{{ stage.count }} · {{ funnelPercent(stage.count) }} %</span>
              </div>
              <div class="mt-1 h-2 rounded-full bg-elevated overflow-hidden">
                <div class="h-full bg-primary/70 rounded-full" :style="{ width: `${funnelPercent(stage.count)}%` }" />
              </div>
            </li>
          </ul>
        </div>
      </div>
    </section>

    <!-- Billing : abonnés par statut + revenu mensuel estimé -->
    <section v-if="billing" class="mt-8" :aria-label="t('admin.billing.title')">
      <h2 class="text-sm font-semibold">{{ t('admin.billing.title') }}</h2>
      <div class="mt-3 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        <div class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t('admin.billing.mrr') }}</p>
          <p class="text-2xl font-semibold font-mono tabular-nums">{{ euroFormat.format(billing.estimatedMonthlyRevenue) }}</p>
          <p class="text-[10px] text-dimmed mt-1">{{ t('admin.billing.mrrHint') }}</p>
        </div>
        <div v-for="s in (['active', 'trialing', 'past_due', 'comped', 'canceled'] as const)" :key="s" class="border border-default rounded-xl p-4 bg-elevated/40">
          <p class="text-xs text-muted">{{ t(`admin.billing.status.${s}`) }}</p>
          <p class="text-2xl font-semibold font-mono tabular-nums">{{ billing.byStatus[s] }}</p>
        </div>
      </div>
    </section>

    <!-- Comptes -->
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
                <button type="button" class="text-left hover:text-primary hover:underline underline-offset-2" @click="openDetail(account)">
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
    </section>

    <!-- Journal d'audit (hors tenant) : actions sensibles tracées -->
    <section class="mt-8" :aria-label="t('admin.audit.title')">
      <h2 class="text-sm font-semibold">{{ t('admin.audit.title') }}</h2>
      <p class="text-xs text-muted mt-1">{{ t('admin.audit.hint') }}</p>
      <div class="mt-3 border border-default rounded-xl overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-default text-left text-xs text-muted">
              <th class="px-3 py-2 font-medium">{{ t('admin.audit.when') }}</th>
              <th class="px-3 py-2 font-medium">{{ t('admin.audit.actor') }}</th>
              <th class="px-3 py-2 font-medium">{{ t('admin.audit.action') }}</th>
              <th class="px-3 py-2 font-medium">{{ t('admin.audit.target') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="auditEntries.length === 0">
              <td colspan="4" class="px-3 py-6 text-center text-muted">{{ t('admin.audit.empty') }}</td>
            </tr>
            <tr v-for="entry in auditEntries" :key="entry.id" class="border-b border-default last:border-0">
              <td class="px-3 py-2 text-muted whitespace-nowrap">{{ formatDateTime(entry.occurredAt) }}</td>
              <td class="px-3 py-2">{{ entry.actor }}</td>
              <td class="px-3 py-2 font-mono text-xs">{{ entry.action }}</td>
              <td class="px-3 py-2 font-mono text-xs text-muted">{{ entry.target }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>

    <!-- Fiche compte détaillée (support) -->
    <UModal v-model:open="detailOpen" :title="detail?.email ?? t('admin.detail.title')">
      <template #body>
        <div v-if="detailLoading" role="status" class="py-6 text-center text-muted">{{ t('common.loading') }}</div>
        <dl v-else-if="detail" class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
          <dt class="text-muted">{{ t('admin.detail.status') }}</dt>
          <dd>
            <span v-if="detail.deletionRequestedAt" class="text-error">{{ t('admin.detail.deleting') }}</span>
            <span v-else>{{ detail.emailVerified ? t('admin.accounts.active') : t('admin.accounts.unverified') }}</span>
          </dd>
          <dt class="text-muted">{{ t('admin.detail.createdAt') }}</dt>
          <dd>{{ detail.createdAt ? formatDate(detail.createdAt) : '—' }}</dd>
          <dt class="text-muted">{{ t('admin.detail.lastLogin') }}</dt>
          <dd>{{ detail.lastLoginAt ? formatDateTime(detail.lastLoginAt) : t('admin.detail.never') }}</dd>
          <dt class="text-muted">{{ t('admin.detail.lastActivity') }}</dt>
          <dd>{{ detail.lastActivityAt ? formatDateTime(detail.lastActivityAt) : t('admin.detail.never') }}</dd>
          <dt class="text-muted">{{ t('admin.detail.twoFactor') }}</dt>
          <dd>{{ detail.twoFactorEnabled ? t('admin.detail.on') : t('admin.detail.off') }}</dd>
          <dt class="text-muted">{{ t('admin.detail.digest') }}</dt>
          <dd class="font-mono text-xs">{{ detail.digestFrequency }}</dd>
          <dt class="text-muted">{{ t('admin.detail.mailbox') }}</dt>
          <dd>{{ detail.mailbox ? `${detail.mailbox.provider} · ${detail.mailbox.status}` : t('admin.detail.noMailbox') }}</dd>
          <dt class="text-muted">{{ t('admin.detail.volumes') }}</dt>
          <dd class="font-mono tabular-nums text-xs">{{ t('admin.detail.volumesValue', { orgs: detail.organizations, leads: detail.leads, sent: detail.messagesSent }) }}</dd>
          <dt class="text-muted">{{ t('admin.detail.subscription') }}</dt>
          <dd>{{ t(`settings.billing.status.${detail.subscriptionStatus}`) }}</dd>
        </dl>
        <div v-if="detail" class="mt-4 pt-3 border-t border-default flex items-center justify-between gap-3">
          <span class="text-xs text-muted">{{ t('admin.detail.compHint') }}</span>
          <UButton
            v-if="detail.subscriptionStatus === 'comped'"
            size="xs"
            color="warning"
            variant="soft"
            :loading="compBusy"
            @click="toggleComp(false)"
          >
            {{ t('admin.detail.compRevoke') }}
          </UButton>
          <UButton v-else size="xs" variant="soft" :loading="compBusy" @click="toggleComp(true)">
            {{ t('admin.detail.compGrant') }}
          </UButton>
        </div>
      </template>
    </UModal>

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
