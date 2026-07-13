<?php

declare(strict_types=1);

namespace App\Shared\Domain\Uid;

/**
 * Génération d'UUID v4 en PHP pur (random_bytes) — le domaine ne dépend pas
 * de symfony/uid. Sert aux identifiants d'events (idempotence des projections).
 */
final class UuidV4
{
    public static function generate(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x40); // version 4
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80); // variante RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
