<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\PollAlertSource;

use App\Shared\Application\Command\Command;

/** Relève la source d'annonces configurée pour un tenant et ingère les items trouvés. */
final class PollAlertSource implements Command
{
    public function __construct(
        public readonly string $tenantId,
    ) {
    }
}
