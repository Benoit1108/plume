<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Persistence;

use App\Notification\Application\NotificationMarker;
use Doctrine\DBAL\Connection;

/**
 * Marquage lu (DBAL pur, prédicat tenant EXPLICITE en plus de la RLS — deux lignes de défense).
 * Idempotent : re-marquer une notification lue est un no-op (`read_at` conservé au premier marquage).
 */
final class DbalNotificationMarker implements NotificationMarker
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function markRead(string $tenantId, string $notificationId): void
    {
        $this->connection->executeStatement(
            'UPDATE notification SET read_at = NOW() WHERE id = :id AND tenant_id = :tenant AND read_at IS NULL',
            ['id' => $notificationId, 'tenant' => $tenantId],
        );
    }

    public function markAllRead(string $tenantId): void
    {
        $this->connection->executeStatement(
            'UPDATE notification SET read_at = NOW() WHERE tenant_id = :tenant AND read_at IS NULL',
            ['tenant' => $tenantId],
        );
    }
}
