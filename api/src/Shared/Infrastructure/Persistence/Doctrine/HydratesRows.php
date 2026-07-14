<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

/**
 * Hydratation de lignes SQL (DBAL) — sémantique UNIQUE pour tous les read
 * models et gateways (la duplication avait déjà fait diverger trim/'' vs null).
 */
trait HydratesRows
{
    /** @param array<string, mixed> $row */
    private function str(array $row, string $key): string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) ? $value : '';
    }

    /**
     * Chaîne vide → null (sans trim : le contenu est restitué tel quel).
     *
     * @param array<string, mixed> $row
     */
    private function strOrNull(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) && '' !== $value ? $value : null;
    }

    /**
     * Blanc (espaces compris) → null ; sinon la valeur telle quelle.
     *
     * @param array<string, mixed> $row
     */
    private function blankToNull(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;

        return \is_string($value) && '' !== trim($value) ? $value : null;
    }

    /** @param array<string, mixed> $row */
    private function int(array $row, string $key): int
    {
        $value = $row[$key] ?? null;

        return is_numeric($value) ? (int) $value : 0;
    }

    /** Booléens Postgres ('t'/'f', 0/1, true/false). */
    private function bool(mixed $value): bool
    {
        return true === $value || 't' === $value || '1' === $value || 1 === $value;
    }

    /** @param array<string, mixed> $row */
    private function date(array $row, string $key): ?\DateTimeImmutable
    {
        $value = $row[$key] ?? null;

        return \is_string($value) && '' !== $value ? new \DateTimeImmutable($value) : null;
    }
}
