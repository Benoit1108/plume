<script setup lang="ts">
import type { AdminAuditEntry } from '~/types/admin'

/** Back-office — journal d'audit hors tenant : les 100 dernières actions sensibles. */
const { t, locale } = useI18n()
const adminApi = useAdmin()

const { data: auditData } = useQuery({ queryKey: queryKeys.adminAudit, queryFn: () => adminApi.audit() })
const auditEntries = computed<AdminAuditEntry[]>(() => auditData.value ?? [])
function formatDateTime(iso: string): string {
  return new Date(iso).toLocaleString(locale.value, { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
}
</script>

<template>
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
</template>
