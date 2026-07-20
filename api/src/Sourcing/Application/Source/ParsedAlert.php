<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Source;

/**
 * Annonce extraite d'une source, prête à être ingérée. Champs best-effort et bornés
 * (le parser garantit un `title` non vide et des longueurs saines). `externalId` = guid
 * stable de l'annonce (RSS) → dédoublonnage fiable ; `rawPayload` = item brut conservé.
 */
final class ParsedAlert
{
    public function __construct(
        public readonly string $source,
        public readonly string $title,
        public readonly ?string $organizationName = null,
        public readonly ?string $languagePair = null,
        public readonly ?string $url = null,
        public readonly ?string $excerpt = null,
        public readonly ?string $externalId = null,
        public readonly ?string $postedAt = null,
        public readonly ?string $rawPayload = null,
    ) {
    }
}
