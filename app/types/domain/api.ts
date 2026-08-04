/** Collection JSON-LD d'API Platform (clé `member`, alias legacy `hydra:member`). */
export interface JsonLdCollection<T> {
  'member'?: T[]
  'hydra:member'?: T[]
}
