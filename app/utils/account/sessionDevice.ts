/**
 * Libellé d'appareil d'une session : « Firefox · Linux », ou ce qu'on sait, ou un repli traduit.
 * Sans lui, les sessions sont interchangeables et « révoquez ce que vous ne reconnaissez pas »
 * (page Compte) est impossible à suivre.
 */
export function formatSessionDevice(
  session: { browser?: string | null, platform?: string | null },
  unknownLabel: string,
): string {
  const parts = [session.browser, session.platform].filter((part): part is string => !!part && part.trim() !== '')

  return parts.length > 0 ? parts.join(' · ') : unknownLabel
}
