<?php

declare(strict_types=1);

namespace App\Notification\Application\Command\MarkNotificationRead;

use App\Shared\Application\Command\Command;

/** Marquer UNE notification comme lue (idempotent). */
final class MarkNotificationRead implements Command
{
    public function __construct(
        public readonly string $tenantId,
        public readonly string $notificationId,
    ) {
    }
}
