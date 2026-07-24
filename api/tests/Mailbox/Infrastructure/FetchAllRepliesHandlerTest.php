<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\Command\FetchReplies\FetchReplies;
use App\Mailbox\Infrastructure\Scheduler\FetchAllRepliesHandler;
use App\Mailbox\Infrastructure\Scheduler\FetchAllRepliesTick;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Le tick de relève des réponses ne fait que du FAN-OUT ASYNCHRONE par tenant : chaque
 * FetchReplies part sur la file `io` (worker plume_app dédié, tenant activé → RLS, isolation de
 * charge ADR-0022 §5) — jamais de logique tenantée dans le scheduler propriétaire (ADR-0023 §5).
 */
final class FetchAllRepliesHandlerTest extends TestCase
{
    public function testDispatchesOneAsyncFetchPerConnectedMailbox(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['tenant-a', 'tenant-b']);

        /** @var list<array{msg: object, stamps: list<object>}> $dispatched */
        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $msg, array $stamps = []) use (&$dispatched): Envelope {
            $dispatched[] = ['msg' => $msg, 'stamps' => $stamps];

            return new Envelope($msg);
        });

        (new FetchAllRepliesHandler($connection, $bus))(new FetchAllRepliesTick());

        self::assertCount(2, $dispatched);
        $first = $dispatched[0]['msg'];
        self::assertInstanceOf(FetchReplies::class, $first);
        self::assertSame('tenant-a', $first->tenantId);
        $stamp = $dispatched[0]['stamps'][0];
        self::assertInstanceOf(TransportNamesStamp::class, $stamp);
        self::assertSame(['io'], $stamp->getTransportNames());
    }
}
