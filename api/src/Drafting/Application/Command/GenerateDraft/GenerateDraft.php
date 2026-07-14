<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\GenerateDraft;

use App\Shared\Application\Command\Command;

final class GenerateDraft implements Command
{
    public function __construct(
        public readonly string $id,
        public readonly string $tenantId,
        public readonly string $leadId,
        public readonly string $type,
        public readonly string $targetLanguage,
        public readonly ?string $templateId,
    ) {
    }
}
