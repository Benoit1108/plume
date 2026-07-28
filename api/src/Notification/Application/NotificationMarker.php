<?php

declare(strict_types=1);

namespace App\Notification\Application;

/** Port d'écriture du centre de notifications : marquage lu (idempotent, scopé tenant). */
interface NotificationMarker
{
    public function markRead(string $tenantId, string $notificationId): void;

    public function markAllRead(string $tenantId): void;
}
