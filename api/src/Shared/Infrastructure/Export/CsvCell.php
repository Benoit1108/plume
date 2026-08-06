<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Export;

/**
 * Neutralise l'injection de formules dans un CSV (revue P3) : un tableur interprète toute cellule
 * commençant par `=`, `+`, `-`, `@`, une tabulation ou un retour chariot comme une FORMULE — pas
 * comme du texte. Une valeur saisie par un tiers (nom d'organisation, note, email d'inscription)
 * peut donc s'exécuter à l'ouverture du fichier, chez la personne qui l'ouvre.
 *
 * Parade recommandée par l'OWASP : préfixer d'une apostrophe simple, que le tableur consomme à
 * l'affichage. On ne touche QUE les valeurs à risque : les nombres et les dates restent intacts.
 */
final class CsvCell
{
    private const string DANGEROUS_PREFIXES = "=+-@\t\r";

    public static function safe(mixed $value): string
    {
        if (null === $value) {
            return '';
        }
        if (\is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (\is_int($value) || \is_float($value)) {
            return (string) $value; // un nombre ne peut pas être une formule
        }

        $text = \is_scalar($value) ? (string) $value : '';

        return '' !== $text && str_contains(self::DANGEROUS_PREFIXES, $text[0]) ? "'".$text : $text;
    }

    /**
     * @param list<mixed> $row
     *
     * @return list<string>
     */
    public static function safeRow(array $row): array
    {
        return array_map(static fn (mixed $cell): string => self::safe($cell), $row);
    }
}
