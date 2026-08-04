import type { OrganizationType } from '~/types/directory'

export const ORGANIZATION_TYPES: OrganizationType[] = ['PUBLISHER', 'AV_STUDIO', 'AGENCY', 'OTHER']
export const SEGMENTS = ['PUBLISHING', 'AUDIOVISUAL', 'TECHNICAL', 'OTHER'] as const

/**
 * Terminologie métier du Répertoire — source unique (i18n) pour les libellés
 * des types et segments, partagée entre liste, fiche et formulaires.
 */
export function useDirectoryLabels() {
  const { t } = useI18n()

  const typeLabel = (type: string): string => t(`directory.types.${type}`, type)
  const segmentLabel = (segment: string): string => t(`directory.segments.${segment}`, segment)

  const typeOptions = computed(() => ORGANIZATION_TYPES.map(type => ({ value: type, label: typeLabel(type) })))
  const segmentOptions = computed(() => SEGMENTS.map(segment => ({ value: segment, label: segmentLabel(segment) })))

  return { typeLabel, segmentLabel, typeOptions, segmentOptions }
}
