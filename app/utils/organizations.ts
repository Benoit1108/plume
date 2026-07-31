/** Logique PURE d'aide au tri (testée — l'affichage reste dans le composant). */

function normalize(value: string): string {
  return value.trim().toLowerCase().replace(/\s+/g, ' ')
}

/**
 * Dédoublonnage suggéré : parmi les organisations existantes, celles dont le nom ressemble à celui
 * qu'on s'apprête à créer (égalité ou inclusion mutuelle, insensible à la casse/espaces). Sert à
 * proposer de RÉUTILISER une organisation plutôt que d'en créer un doublon. Bornée (5 max) et muette
 * en dessous de 3 caractères (trop de bruit).
 */
export function suggestDuplicateOrganizations<T extends { name: string }>(name: string, organizations: readonly T[]): T[] {
  const query = normalize(name)
  if (query.length < 3) return []

  return organizations
    .filter((org) => {
      const candidate = normalize(org.name)
      return candidate !== '' && (candidate === query || candidate.includes(query) || query.includes(candidate))
    })
    .slice(0, 5)
}
