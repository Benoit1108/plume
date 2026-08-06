/** Règles exigées, dans l'ordre d'affichage. Miroir exact de `PasswordPolicy` côté API. */
export const PASSWORD_RULES = ['length', 'lowercase', 'uppercase', 'special'] as const

export type PasswordRule = typeof PASSWORD_RULES[number]

export const PASSWORD_MIN_LENGTH = 8

export interface PasswordAssessment {
  /** Règle par règle : respectée ou non — c'est ce que l'écran coche en direct. */
  rules: Record<PasswordRule, boolean>
  /** Toutes les règles sont tenues : le formulaire peut être envoyé. */
  satisfied: boolean
  /** Robustesse 0–4, au-delà des règles minimales (longueur, variété, chiffres). */
  score: number
}

/**
 * Évalue un mot de passe SANS le juger prématurément : les règles sont binaires (on les affiche
 * cochées ou non), la robustesse est un indice séparé. Confondre les deux donne une barre verte
 * sur un mot de passe refusé — ou l'inverse.
 */
export function assessPassword(password: string): PasswordAssessment {
  const rules: Record<PasswordRule, boolean> = {
    length: password.length >= PASSWORD_MIN_LENGTH,
    lowercase: /\p{Ll}/u.test(password),
    uppercase: /\p{Lu}/u.test(password),
    special: /[^\p{L}\p{N}\s]/u.test(password),
  }

  return { rules, satisfied: PASSWORD_RULES.every(rule => rules[rule]), score: strengthScore(password) }
}

/**
 * Indice de robustesse 0–4. Volontairement simple et local (aucune dépendance, aucun appel
 * réseau) : longueur d'abord — c'est ce qui compte le plus — puis variété de caractères.
 */
function strengthScore(password: string): number {
  if (password === '') return 0

  let points = 0
  if (password.length >= PASSWORD_MIN_LENGTH) points += 1
  if (password.length >= 12) points += 1
  if (password.length >= 16) points += 1

  const families = [/\p{Ll}/u, /\p{Lu}/u, /\p{N}/u, /[^\p{L}\p{N}\s]/u].filter(re => re.test(password)).length
  if (families >= 3) points += 1
  if (families === 4) points += 1

  // Une répétition évidente (« aaaaaaaa », « ababab ») ne vaut pas sa longueur.
  if (/^(.+?)\1+$/.test(password)) points = Math.min(points, 1)

  return Math.min(4, points)
}
