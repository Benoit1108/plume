<?php

declare(strict_types=1);

namespace App\Notification\Application\Command\MarkAllNotificationsRead;

use App\Notification\Application\NotificationMarker;
use App\Shared\Application\Command\CommandHandler;

final class MarkAllNotificationsReadHandler implements CommandHandler
{
    public function __construct(private readonly NotificationMarker $marker)
    {
    }

    public function __invoke(MarkAllNotificationsRead $command): void
    {
        $this->marker->markAllRead($command->tenantId);
    }
}
