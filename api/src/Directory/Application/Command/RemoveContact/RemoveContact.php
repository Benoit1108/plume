<?php

declare(strict_types=1);

namespace App\Directory\Application\Command\RemoveContact;

use App\Shared\Application\Command\Command;

final class RemoveContact implements Command
{
    public function __construct(
        public readonly string $organizationId,
        public readonly string $contactId,
    ) {
    }
}
