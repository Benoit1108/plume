<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Template\Event;

use App\Shared\Domain\AbstractDomainEvent;

final class TemplateUpdated extends AbstractDomainEvent
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $templateId,
        \DateTimeImmutable $occurredOn,
    ) {
        parent::__construct($occurredOn);
    }
}
