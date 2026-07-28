<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\Infrastructure\Scheduler\PurgeAccount;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

/**
 * RGPD — purge d'UN compte VIA LE COMMAND.BUS (donc sous `doctrine_transaction` : une transaction
 * par message, le vrai chemin de prod). Efface toutes les données tenantées + refresh tokens +
 * app_user, en laissant les autres comptes intacts. Ce test passe par le bus (et non par une
 * instanciation directe) pour couvrir l'atomicité par compte — le défaut P1 de la revue V2.0.
 */
final class PurgeAccountHandlerTest extends KernelTestCase
{
    private const TARGET = '11111111-1111-1111-1111-111111111111';
    private const OTHER = '22222222-2222-2222-2222-222222222222';

    public function testPurgesOneAccountThroughTheBusLeavingOthersIntact(): void
    {
        $container = static::getContainer();
        $connection = $container->get(Connection::class);
        \assert($connection instanceof Connection);
        $bus = $container->get('command.bus');
        \assert($bus instanceof MessageBusInterface);

        $connection->executeStatement('TRUNCATE TABLE app_user, alert_feed, refresh_tokens RESTART IDENTITY CASCADE');
        $this->seed($connection, self::TARGET, 'target@plume.test');
        $this->seed($connection, self::OTHER, 'other@plume.test');

        $bus->dispatch(new PurgeAccount(self::TARGET, 'target@plume.test'));

        // Le compte ciblé a totalement disparu.
        self::assertSame(0, $this->rowCount($connection, 'app_user', self::TARGET));
        self::assertSame(0, $this->rowCount($connection, 'alert_feed', self::TARGET));
        self::assertSame(0, $this->tokenCount($connection, 'target@plume.test'));

        // L'autre compte est intact.
        self::assertSame(1, $this->rowCount($connection, 'app_user', self::OTHER));
        self::assertSame(1, $this->rowCount($connection, 'alert_feed', self::OTHER));
        self::assertSame(1, $this->tokenCount($connection, 'other@plume.test'));
    }

    private function seed(Connection $c, string $tenant, string $email): void
    {
        $c->executeStatement(
            'INSERT INTO app_user (id, tenant_id, email, password, roles) VALUES (?, ?, ?, ?, ?)',
            [Uuid::v7()->toRfc4122(), $tenant, $email, 'x', '[]'],
        );
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

    private function rowCount(Connection $c, string $table, string $tenant): int
    {
        $value = $c->fetchOne(\sprintf('SELECT COUNT(*) FROM %s WHERE tenant_id = ?', $c->quoteIdentifier($table)), [$tenant]);

        return is_numeric($value) ? (int) $value : -1;
    }

    private function tokenCount(Connection $c, string $email): int
    {
        $value = $c->fetchOne('SELECT COUNT(*) FROM refresh_tokens WHERE username = ?', [$email]);

        return is_numeric($value) ? (int) $value : -1;
    }
}
