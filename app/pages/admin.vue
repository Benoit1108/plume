<script setup lang="ts">
import type { AdminAccount } from '~/types/domain/admin'

/**
 * Back-office (ROLE_ADMIN). L'entrée nav n'apparaît qu'aux admins ; l'autorité reste l'API (403 sinon).
 * Orchestrateur : chaque section est un composant autonome portant sa query. Seul état partagé ici :
 * le compte dont la fiche détaillée est ouverte (table → modal). Découpage revue santé (lot F).
 */
const { t } = useI18n()

const detailId = ref<string | null>(null)
const detailOpen = computed({
  get: () => detailId.value !== null,
  set: (open: boolean) => { if (!open) detailId.value = null },
})
function openDetail(account: AdminAccount): void {
  detailId.value = account.tenantId
}
</script>

<template>
  <PageContainer width="atelier">
    <PageHeader :eyebrow="t('admin.eyebrow')" :title="t('admin.title')" />

    <AdminOverviewCards />
    <AdminSystemStatus />
    <AdminAlerts />
    <AdminMetrics />
    <AdminTrends />
    <AdminBilling />
    <AdminAccountsTable @select="openDetail" />
    <AdminAuditTable />

    <AdminAccountDetailModal v-model:open="detailOpen" :tenant-id="detailId" />
  </PageContainer>
</template>
