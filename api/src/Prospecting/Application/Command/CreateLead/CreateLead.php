<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\CreateLead;

use App\Shared\Application\Command\Command;

final class CreateLead implements Command
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $organizationId,
        public readonly ?string $contactId,
        public readonly string $languagePair,
        public readonly string $source,
        public readonly string $priority,
        public readonly string $segment,
    ) {
    }
}
