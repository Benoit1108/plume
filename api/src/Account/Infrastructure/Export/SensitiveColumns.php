<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Export;

/**
 * RGPD — classification des colonnes JAMAIS exportées (secrets/credentials), par MOTIF plutôt que
 * par liste figée (revue V2.0). La découverte des tables exportées étant dynamique, une détection
 * statique laisserait fuir toute future colonne sensible nommée autrement (`webhook_secret`,
 * `api_key`, `id_token`, `encrypted_*`…) : le motif ferme cette asymétrie — deny-by-pattern.
 */
final class SensitiveColumns
{
    /**
     * Motifs de noms de colonnes à ne jamais exporter (insensibles à la casse) :
     * tout ce qui contient token/secret/password/credential/api_key, ou préfixé `encrypted_`,
     * ou suffixé `_cursor` (curseurs de synchronisation opaques).
     */
    private const array PATTERNS = [
        '/token/i',
        '/secret/i',
        '/password/i',
        '/passwd/i',
        '/credential/i',
        '/api_?key/i',
        '/^encrypted_/i',
        '/_cursor$/i',
    ];

    public static function isSensitive(string $column): bool
    {
        foreach (self::PATTERNS as $pattern) {
            if (1 === preg_match($pattern, $column)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Retire les colonnes sensibles d'une ligne (clés = noms de colonnes).
     *
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    public static function strip(array $row): array
    {
        return array_filter(
            $row,
            static fn (string $column): bool => !self::isSensitive($column),
            \ARRAY_FILTER_USE_KEY,
        );
    }
}
