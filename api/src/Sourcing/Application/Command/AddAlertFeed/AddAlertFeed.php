<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\AddAlertFeed;

use App\Shared\Application\Command\Command;

/** Ajoute un flux d'annonces (RSS) à la configuration du tenant. */
final class AddAlertFeed implements Command
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $source,
        public readonly string $url,
        public readonly ?string $label = null,
    ) {
    }
}
