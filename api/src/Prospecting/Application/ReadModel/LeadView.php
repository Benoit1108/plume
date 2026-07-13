<?php

declare(strict_types=1);

namespace App\Prospecting\Application\ReadModel;

/** Vue de lecture d'une piste — immuable, découplée de l'agrégat (ADR-0013). */
final class LeadView
{
    /** @param string[] $allowedActions actions de transition proposables (contact, reply, win…) */
    public function __construct(
        public readonly string $id,
        public readonly string $organizationId,
        public readonly string $organizationName,
        public readonly ?string $contactId,
        public readonly string $languagePair,
        public readonly string $source,
        public readonly string $priority,
        public readonly string $segment,
        public readonly string $status,
        public readonly array $allowedActions,
        public readonly \DateTimeImmutable $createdAt,
        public readonly ?\DateTimeImmutable $lastContactedAt,
        public readonly ?\DateTimeImmutable $lastReplyAt,
    ) {
    }
}
