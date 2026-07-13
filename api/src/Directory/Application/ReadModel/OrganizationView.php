<?php

declare(strict_types=1);

namespace App\Directory\Application\ReadModel;

/**
 * Vue de lecture d'une organisation. Les queries retournent ces vues immuables,
 * jamais l'agrégat (entité managée) — cf. ADR-0013.
 */
final class OrganizationView
{
    /**
     * @param string[]      $workingLanguages
     * @param string[]      $segments
     * @param ContactView[] $contacts
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $website,
        public readonly ?string $country,
        public readonly array $workingLanguages,
        public readonly array $segments,
        public readonly ?string $notes,
        public readonly bool $doNotContact,
        public readonly array $contacts,
    ) {
    }
}
