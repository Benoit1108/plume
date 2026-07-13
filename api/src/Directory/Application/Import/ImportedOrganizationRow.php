<?php

declare(strict_types=1);

namespace App\Directory\Application\Import;

/** Une ligne d'import déjà nettoyée/normalisée (produite par le parser CSV d'Infrastructure). */
final class ImportedOrganizationRow
{
    /**
     * @param string[] $languages codes ISO 639-1 validés
     * @param string[] $segments  valeurs d'enum Segment
     */
    public function __construct(
        public readonly int $line,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $website,
        public readonly ?string $country,
        public readonly array $languages,
        public readonly array $segments,
        public readonly ?string $notes,
        public readonly ?string $contactName,
        public readonly ?string $contactRole,
        public readonly ?string $contactEmail,
        public readonly ?string $contactPhone,
    ) {
    }
}
