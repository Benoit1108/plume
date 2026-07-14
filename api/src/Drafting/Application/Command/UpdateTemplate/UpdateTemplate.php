<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\UpdateTemplate;

use App\Shared\Application\Command\Command;

final class UpdateTemplate implements Command
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $type,
        public readonly string $segment,
        public readonly string $language,
        public readonly ?string $subject,
        public readonly string $body,
    ) {
    }
}
