<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\RemoveAlertFeed;

use App\Shared\Application\Command\Command;

/** Retire un flux d'annonces (le tenant est résolu par le SQLFilter au chargement). */
final class RemoveAlertFeed implements Command
{
    public function __construct(
        public readonly string $alertFeedId,
    ) {
    }
}
