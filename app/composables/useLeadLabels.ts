import type { LeadPriority, LeadSource, LeadStatus } from '~/types/leads'

export const LEAD_STATUSES: LeadStatus[] = ['TO_CONTACT', 'CONTACTED', 'FOLLOWED_UP', 'IN_DISCUSSION', 'SAMPLE_TEST', 'WON', 'LOST', 'PAUSED']
export const LEAD_PRIORITIES: LeadPriority[] = ['LOW', 'MEDIUM', 'HIGH']
export const LEAD_SOURCES: LeadSource[] = ['DIRECT', 'REFERRAL', 'JOB_BOARD', 'OTHER']

/** Terminologie du pipeline — source unique (i18n) pour statuts, priorités, origines, actions. */
export function useLeadLabels() {
  const { t } = useI18n()

  const statusLabel = (status: string): string => t(`pipeline.statuses.${status}`, status)
  const priorityLabel = (priority: string): string => t(`pipeline.priorities.${priority}`, priority)
  const sourceLabel = (source: string): string => t(`pipeline.sources.${source}`, source)
  const actionLabel = (action: string): string => t(`pipeline.actions.${action}`, action)

  const priorityOptions = computed(() => LEAD_PRIORITIES.map(value => ({ value, label: priorityLabel(value) })))
  const sourceOptions = computed(() => LEAD_SOURCES.map(value => ({ value, label: sourceLabel(value) })))

  /** « en>fr » → « en → fr » (affichage). */
  const pairLabel = (pair: string): string => pair.replace('>', ' → ')

  return { statusLabel, priorityLabel, sourceLabel, actionLabel, pairLabel, priorityOptions, sourceOptions }
}
