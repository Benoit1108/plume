<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\EditDraft;

use App\Shared\Application\Command\Command;

final class EditDraft implements Command
{
    public function __construct(
        public readonly string $draftId,
        public readonly ?string $subject,
        public readonly string $body,
    ) {
    }
}
