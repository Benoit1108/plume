<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class ReplyReceived extends AbstractDomainEvent
{
    /** @param ?string $preview extrait TEXTE (déjà nettoyé) de la réponse captée, null si geste manuel */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $leadId,
        public readonly ?string $preview,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
