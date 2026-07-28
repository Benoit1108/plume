<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Account\Infrastructure\Scheduler\PurgeAccount;
use App\Account\Infrastructure\Scheduler\PurgeDeletedAccountsHandler;
use App\Account\Infrastructure\Scheduler\PurgeDeletedAccountsTick;
use App\Tests\Support\FixedClock;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Uid\Uuid;

/**
 * RGPD — le tick de purge ne fait que du FAN-OUT : il émet UN message `PurgeAccount` (async, sa
 * propre transaction) par compte dont la suppression date de plus du délai de grâce (30 j) ; un
 * compte encore en grâce ou actif n'émet rien. La purge réelle par compte est testée dans
 * PurgeAccountHandlerTest (via le command.bus).
 */
final class PurgeDeletedAccountsTest extends KernelTestCase
{
    private const EXPIRED = '11111111-1111-1111-1111-111111111111';
    private const GRACE = '22222222-2222-2222-2222-222222222222';
    private const ACTIVE = '33333333-3333-3333-3333-333333333333';

    public function testFansOutOneAsyncPurgePerExpiredAccount(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE app_user RESTART IDENTITY CASCADE');

        $this->seed($connection, self::EXPIRED, 'expired@plume.test', '2026-06-01 10:00:00'); // > 30 j
        $this->seed($connection, self::GRACE, 'grace@plume.test', '2026-07-20 10:00:00');      // < 30 j
        $this->seed($connection, self::ACTIVE, 'active@plume.test', null);                     // actif

        /** @var list<array{msg: object, stamps: list<object>}> $dispatched */
        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $msg, array $stamps = []) use (&$dispatched): Envelope {
            $dispatched[] = ['msg' => $msg, 'stamps' => $stamps];

            return new Envelope($msg);
        });

        (new PurgeDeletedAccountsHandler($connection, new FixedClock(new \DateTimeImmutable('2026-07-28 12:00:00')), $bus))
            (new PurgeDeletedAccountsTick());

        self::assertCount(1, $dispatched);
        $message = $dispatched[0]['msg'];
        self::assertInstanceOf(PurgeAccount::class, $message);
        self::assertSame(self::EXPIRED, $message->tenantId);
        self::assertSame('expired@plume.test', $message->email);
        $stamp = $dispatched[0]['stamps'][0];
        self::assertInstanceOf(TransportNamesStamp::class, $stamp);
        self::assertSame(['async'], $stamp->getTransportNames());
    }

    private function seed(Connection $c, string $tenant, string $email, ?string $deletedAt): void
    {
        $c->executeStatement(
            'INSERT INTO app_user (id, tenant_id, email, password, roles, deletion_requested_at) VALUES (?, ?, ?, ?, ?, ?)',
            [Uuid::v7()->toRfc4122(), $tenant, $email, 'x', '[]', $deletedAt],
        );
    }
}
