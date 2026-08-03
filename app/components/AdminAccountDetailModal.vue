<script setup lang="ts">
import type { AdminAccountDetail } from '~/types/admin'

/**
 * Back-office — fiche compte détaillée (support) : ouverte au clic sur un email. Charge le détail à
 * la demande (query activée quand `tenantId` est posé) et permet d'offrir / retirer l'accès (comped).
 */
const props = defineProps<{ tenantId: string | null }>()
const open = defineModel<boolean>('open', { required: true })

const { t, locale } = useI18n()
const adminApi = useAdmin()
const toast = useToast()
const queryClient = useQueryClient()

const { data: detailData, isPending: detailLoading } = useQuery({
  queryKey: computed(() => queryKeys.adminAccount(props.tenantId ?? '')),
  queryFn: () => adminApi.accountDetail(props.tenantId as string),
  enabled: computed(() => props.tenantId !== null),
})
const detail = computed<AdminAccountDetail | null>(() => detailData.value ?? null)

const compBusy = ref(false)
async function toggleComp(comped: boolean): Promise<void> {
  if (compBusy.value || !props.tenantId) return
  compBusy.value = true
  try {
    await adminApi.setComp(props.tenantId, comped)
    await queryClient.invalidateQueries({ queryKey: queryKeys.adminAccount(props.tenantId) })
    await queryClient.invalidateQueries({ queryKey: queryKeys.adminBilling })
    toast.add({ title: comped ? t('admin.detail.compGranted') : t('admin.detail.compRevoked'), color: 'success' })
  }
  catch { toast.add({ title: t('common.error'), color: 'error' }) }
  finally { compBusy.value = false }
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' })
}
function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString(locale.value, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
  <UModal v-model:open="open" :title="detail?.email ?? t('admin.detail.title')">
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
</template>
