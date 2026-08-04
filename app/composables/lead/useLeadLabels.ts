import type { LeadPriority, LeadSource, LeadStatus } from '~/types/domain/leads'

export const LEAD_STATUSES: LeadStatus[] = ['TO_CONTACT', 'CONTACTED', 'FOLLOWED_UP', 'IN_DISCUSSION', 'SAMPLE_TEST', 'WON', 'LOST', 'PAUSED']
export const LEAD_PRIORITIES: LeadPriority[] = ['LOW', 'MEDIUM', 'HIGH']
export const LEAD_SOURCES: LeadSource[] = ['DIRECT', 'REFERRAL', 'JOB_BOARD', 'OTHER']

/** Terminologie du pipeline — source unique pour statuts, priorités, origines, actions.
 *  Les LIBELLÉS DE STATUT peuvent être personnalisés par tenant (ADR-0031) : override du profil,
 *  sinon i18n. Le profil est lu via une query partagée (cache TanStack — un seul fetch réutilisé). */
export function useLeadLabels() {
  const { t } = useI18n()
  const profileApi = useProfile()
  const { data: profile } = useQuery({ queryKey: queryKeys.profile, queryFn: () => profileApi.get() })
  const customStatusLabels = computed<Record<string, string>>(() => profile.value?.pipelineLabels ?? {})

  const statusLabel = (status: string): string => customStatusLabels.value[status] || t(`pipeline.statuses.${status}`, status)
  const priorityLabel = (priority: string): string => t(`pipeline.priorities.${priority}`, priority)
  const sourceLabel = (source: string): string => t(`pipeline.sources.${source}`, source)
  const actionLabel = (action: string): string => t(`pipeline.actions.${action}`, action)

  const priorityOptions = computed(() => LEAD_PRIORITIES.map(value => ({ value, label: priorityLabel(value) })))
  const sourceOptions = computed(() => LEAD_SOURCES.map(value => ({ value, label: sourceLabel(value) })))

  /** « en>fr » → « en → fr » (affichage). */
  const pairLabel = (pair: string): string => pair.replace('>', ' → ')

  return { statusLabel, priorityLabel, sourceLabel, actionLabel, pairLabel, priorityOptions, sourceOptions }
}
