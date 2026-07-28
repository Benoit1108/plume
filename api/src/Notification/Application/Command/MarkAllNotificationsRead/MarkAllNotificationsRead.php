<?php

declare(strict_types=1);

namespace App\Notification\Application\Command\MarkAllNotificationsRead;

use App\Shared\Application\Command\Command;

/** Marquer TOUTES les notifications du tenant comme lues (idempotent). */
final class MarkAllNotificationsRead implements Command
{
    public function __construct(public readonly string $tenantId)
    {
    }
}
