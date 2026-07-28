<?php

declare(strict_types=1);

namespace App\Notification\Application\Command\MarkNotificationRead;

use App\Notification\Application\NotificationMarker;
use App\Shared\Application\Command\CommandHandler;

final class MarkNotificationReadHandler implements CommandHandler
{
    public function __construct(private readonly NotificationMarker $marker)
    {
    }

    public function __invoke(MarkNotificationRead $command): void
    {
        $this->marker->markRead($command->tenantId, $command->notificationId);
    }
}
