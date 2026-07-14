<?php

declare(strict_types=1);

namespace App\Drafting\Application;

/** Photo minimale d'une piste et de sa cible, vue depuis Drafting. */
final class LeadContext
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $organizationName,
        public readonly string $segment,
        public readonly string $languagePair,
        public readonly string $status,
        public readonly ?string $contactName,
        public readonly bool $contactAllowed,
    ) {
    }
}
