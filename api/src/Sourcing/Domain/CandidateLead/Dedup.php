<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\CandidateLead;

/**
 * Empreinte de déduplication d'une annonce (ADR-0021). Identifiant stable de la
 * source s'il existe (GUID RSS, ID d'offre) ; sinon nom d'organisation normalisé
 * + titre. Deux ingestions de la même annonce → même empreinte → no-op.
 */
final class Dedup
{
    public static function hash(Source $source, ?string $externalId, ?string $organizationName, string $title): string
    {
        $key = null !== $externalId && '' !== trim($externalId)
            ? $source->value.'|'.self::normalize($externalId)
            : $source->value.'|'.self::normalize($organizationName ?? '').'|'.self::normalize($title);

        return hash('sha256', $key);
    }

    private static function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }
}
