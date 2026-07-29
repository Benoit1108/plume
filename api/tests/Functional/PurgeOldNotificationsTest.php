<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Notification\Infrastructure\Scheduler\PurgeOldNotificationsHandler;
use App\Notification\Infrastructure\Scheduler\PurgeOldNotificationsTick;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * Rétention (revue globale perf P1-2) : les notifications LUES de plus de 90 jours sont purgées ;
 * les non-lues (même anciennes) et les lues récentes restent ; les jetons de reset expirés sont nettoyés.
 */
final class PurgeOldNotificationsTest extends KernelTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE notification, password_reset_token RESTART IDENTITY CASCADE');
    }

    public function testPurgesOldReadNotificationsAndExpiredResetTokens(): void
    {
        $tenant = Uuid::v7()->toRfc4122();
        $this->seedNotification($tenant, 'old-read', '2020-01-01 10:00:00', '2020-01-02 10:00:00');   // lue, ancienne → purgée
        $this->seedNotification($tenant, 'old-unread', '2020-01-01 10:00:00', null);                  // NON lue → gardée
        $this->seedNotification($tenant, 'recent-read', 'now', 'now');                                 // lue récente → gardée

        $this->connection->executeStatement(
            "INSERT INTO password_reset_token (id, email, token_hash, expires_at, created_at)
             VALUES (?, 'x@plume.test', 'h1', '2020-01-01 00:00:00', '2020-01-01 00:00:00'),
                    (?, 'y@plume.test', 'h2', '2999-01-01 00:00:00', '2026-07-29 00:00:00')",
            [Uuid::v7()->toRfc4122(), Uuid::v7()->toRfc4122()],
        );

        (new PurgeOldNotificationsHandler($this->connection))(new PurgeOldNotificationsTick());

        $remaining = $this->connection->fetchFirstColumn('SELECT event_id FROM notification ORDER BY event_id');
        self::assertSame(['old-unread', 'recent-read'], $remaining);

        $tokens = $this->connection->fetchOne('SELECT COUNT(*) FROM password_reset_token');
        self::assertSame(1, is_numeric($tokens) ? (int) $tokens : -1); // seul le non-expiré reste
    }

    private function seedNotification(string $tenant, string $eventId, string $occurred, ?string $readAt): void
    {
        $this->connection->executeStatement(
            "INSERT INTO notification (id, event_id, tenant_id, type, payload, occurred_on, read_at)
             VALUES (?, ?, ?, 'reply_received', '{}', ?, ?)",
            [Uuid::v7()->toRfc4122(), $eventId, $tenant, 'now' === $occurred ? date('Y-m-d H:i:s') : $occurred, 'now' === $readAt ? date('Y-m-d H:i:s') : $readAt],
        );
    }
}
