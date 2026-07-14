<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound\Event;

use App\Shared\Domain\AbstractDomainEvent;

/** Réponse entrante rattachée à une piste (threading) — langage publié vers la Prospection. */
final class ReplyCaptured extends AbstractDomainEvent
{
    /** @param string $preview extrait TEXTE court, déjà nettoyé */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $leadId,
        public readonly string $threadKey,
        public readonly string $preview,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
