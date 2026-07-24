<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\Infrastructure\Scheduler\PurgeDeletedAccountsHandler;
use App\Account\Infrastructure\Scheduler\PurgeDeletedAccountsTick;
use App\Tests\Support\FixedClock;
use Doctrine\DBAL\Connection;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

/**
 * RGPD — purge après délai de grâce : seul le compte dont la suppression a été demandée il y a PLUS
 * de 30 jours est effacé (app_user + refresh tokens + toutes ses données tenantées) ; un compte
 * encore dans le délai de grâce, comme un compte actif, est intégralement préservé.
 */
final class PurgeDeletedAccountsTest extends KernelTestCase
{
    private const EXPIRED = '11111111-1111-1111-1111-111111111111';
    private const GRACE = '22222222-2222-2222-2222-222222222222';
    private const ACTIVE = '33333333-3333-3333-3333-333333333333';

    private function seedAccount(Connection $c, string $tenant, string $email, ?string $deletedAt): void
    {
        $c->executeStatement(
            'INSERT INTO app_user (id, tenant_id, email, password, roles, deletion_requested_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Uuid::v7()->toRfc4122(), $tenant, $email, 'x', '[]', $deletedAt],
        );
        // Une donnée tenantée quelconque pour prouver l'effacement en cascade.
        $c->executeStatement(
            "INSERT INTO alert_feed (id, tenant_id, source, url, label, active, created_at)
             VALUES (?, ?, 'rss', 'https://example.test/feed', 'Flux', true, '2026-07-01 10:00:00')",
            [Uuid::v7()->toRfc4122(), $tenant],
        );
        $c->executeStatement(
            "INSERT INTO refresh_tokens (refresh_token, username, valid) VALUES (?, ?, '2027-01-01 00:00:00')",
            [bin2hex(random_bytes(16)), $email],
        );
    }

    public function testPurgesOnlyAccountsPastTheGracePeriod(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE app_user, alert_feed, refresh_tokens RESTART IDENTITY CASCADE');

        $this->seedAccount($connection, self::EXPIRED, 'expired@plume.test', '2026-06-01 10:00:00'); // > 30 j
        $this->seedAccount($connection, self::GRACE, 'grace@plume.test', '2026-07-20 10:00:00');     // < 30 j
        $this->seedAccount($connection, self::ACTIVE, 'active@plume.test', null);                    // actif

        $handler = new PurgeDeletedAccountsHandler(
            $connection,
            new FixedClock(new \DateTimeImmutable('2026-07-24 12:00:00')),
            new NullLogger(),
        );
        $handler(new PurgeDeletedAccountsTick());

        // Le compte expiré a totalement disparu.
        self::assertSame(0, $this->rowCount($connection, 'app_user', self::EXPIRED));
        self::assertSame(0, $this->rowCount($connection, 'alert_feed', self::EXPIRED));
        $tokens = $connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens WHERE username = ?', ['expired@plume.test']);
        self::assertSame(0, is_numeric($tokens) ? (int) $tokens : -1);

        // Le compte encore en délai de grâce et le compte actif sont intacts.
        self::assertSame(1, $this->rowCount($connection, 'app_user', self::GRACE));
        self::assertSame(1, $this->rowCount($connection, 'alert_feed', self::GRACE));
        self::assertSame(1, $this->rowCount($connection, 'app_user', self::ACTIVE));
        self::assertSame(1, $this->rowCount($connection, 'alert_feed', self::ACTIVE));
    }

    private function rowCount(Connection $c, string $table, string $tenant): int
    {
        $value = $c->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE tenant_id = ?', $c->quoteIdentifier($table)), [$tenant]);

        return is_numeric($value) ? (int) $value : -1;
    }
}
