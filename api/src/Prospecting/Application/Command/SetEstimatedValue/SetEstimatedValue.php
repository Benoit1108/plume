<?php

declare(strict_types=1);

namespace App\Prospecting\Application\Command\SetEstimatedValue;

use App\Shared\Application\Command\Command;

/** Fixe (ou efface avec null) la valeur estimée du deal d'une piste. */
final class SetEstimatedValue implements Command
{
    public function __construct(
        public readonly string $leadId,
        public readonly ?int $estimatedValue,
        public readonly ?string $tenantId = null,
    ) {
    }
}
