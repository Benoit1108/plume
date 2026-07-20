<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox\Event;

use App\Shared\Domain\AbstractDomainEvent;

/**
 * Un email d'alerte a été lu sous le label dédié — langage publié vers le Sourcing,
 * qui décide seul comment l'ingérer (jamais d'appel direct inter-contextes).
 */
final class AlertEmailReceived extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $fromAddress,
        public readonly string $subject,
        public readonly string $body,
        public readonly string $externalId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
