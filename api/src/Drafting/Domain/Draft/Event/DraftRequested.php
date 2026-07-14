<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class DraftRequested extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $draftId,
        public readonly string $leadId,
        public readonly string $type,
        public readonly string $targetLanguage,
        public readonly ?string $templateId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
