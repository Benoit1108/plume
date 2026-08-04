import type { components, paths } from './api-generated'

/**
 * Accès ergonomique au contrat OpenAPI généré (`npm run gen:types` → `api-generated.ts`, régénéré
 * et vérifié en CI). Source de vérité du contrat back ; à consommer pour typer les
 * requêtes/réponses (notamment lors de la migration TanStack Query, chantier 3 lot D).
 *
 * NB : la sortie API Platform est volontairement large (statuts en `string`, nullabilité
 * permissive, wrappers JSON-LD). Les types applicatifs (`types/leads.ts`, etc.) restent donc plus
 * PRÉCIS (unions de statuts, non-nullabilité) ; on ne dérive du contrat que là où c'est un gain net.
 */
export type Schemas = components['schemas']
export type ApiPaths = paths
