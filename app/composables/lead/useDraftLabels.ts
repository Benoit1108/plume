import type { DraftStatus, DraftType } from '~/types/drafting'

export const DRAFT_TYPES: DraftType[] = ['APPLICATION_EMAIL', 'COVER_LETTER', 'FOLLOW_UP_EMAIL']

/** Terminologie de la rédaction assistée — source unique (i18n). */
export function useDraftLabels() {
  const { t } = useI18n()

  const typeLabel = (type: string): string => t(`drafts.types.${type}`, type)
  const statusLabel = (status: string): string => t(`drafts.statuses.${status}`, status)
  const failureLabel = (reason: string): string => t(`drafts.failures.${reason}`, t('drafts.failures.generation_failed'))

  const typeOptions = computed(() => DRAFT_TYPES.map(value => ({ value, label: typeLabel(value) })))

  const statusColor = (status: DraftStatus): 'warning' | 'success' | 'error' =>
    status === 'GENERATING' ? 'warning' : status === 'READY' ? 'success' : 'error'

  return { typeLabel, statusLabel, failureLabel, typeOptions, statusColor }
}
