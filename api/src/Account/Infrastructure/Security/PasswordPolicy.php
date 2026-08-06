<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Security;

/**
 * Exigences d'un mot de passe, à UN SEUL endroit : inscription, réinitialisation et changement
 * appliquaient jusqu'ici chacun leur propre longueur minimale, ce qui laissait les trois dériver.
 *
 * Règles : longueur, une minuscule, une majuscule, un caractère spécial. Pas d'exigence de chiffre
 * (choix de Benoit) ; pas de plafond de complexité non plus — la borne haute n'existe que pour
 * refuser un corps de requête déraisonnable, jamais pour brider une phrase de passe.
 *
 * `violations()` renvoie des CODES stables : le front les traduit et coche la même liste que celle
 * qu'il affiche en direct, donc l'utilisatrice ne découvre jamais une règle au moment du refus.
 */
final class PasswordPolicy
{
    public const int MIN_LENGTH = 8;
    public const int MAX_LENGTH = 4096;

    /** Tout ce qui n'est ni lettre ni chiffre ni espace — accents compris côté lettres. */
    private const string SPECIAL_PATTERN = '/[^\p{L}\p{N}\s]/u';

    /**
     * @return list<string> codes des règles NON respectées (vide = mot de passe accepté)
     */
    public static function violations(string $password): array
    {
        $violations = [];
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH) {
            $violations[] = 'too_short';
        }
        if ($length > self::MAX_LENGTH) {
            $violations[] = 'too_long';
        }
        if (1 !== preg_match('/\p{Ll}/u', $password)) {
            $violations[] = 'missing_lowercase';
        }
        if (1 !== preg_match('/\p{Lu}/u', $password)) {
            $violations[] = 'missing_uppercase';
        }
        if (1 !== preg_match(self::SPECIAL_PATTERN, $password)) {
            $violations[] = 'missing_special';
        }

        return $violations;
    }

    public static function isSatisfiedBy(string $password): bool
    {
        return [] === self::violations($password);
    }
}
