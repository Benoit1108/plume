<?php

declare(strict_types=1);

namespace App\Sourcing\Application\Command\SetAlertFeedActive;

use App\Shared\Application\Command\Command;

/** Active ou désactive un flux (seuls les flux actifs sont relevés). */
final class SetAlertFeedActive implements Command
{
    public function __construct(
        public readonly string $alertFeedId,
        public readonly bool $active,
    ) {
    }
}
