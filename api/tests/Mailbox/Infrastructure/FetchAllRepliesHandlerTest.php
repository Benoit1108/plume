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
 * FetchReplies part sur `async` (worker plume_app, tenant activé → RLS) — jamais de logique
 * tenantée exécutée dans le scheduler propriétaire (P1 revue pré-V2, ADR-0023 §5).
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
        self::assertSame(['async'], $stamp->getTransportNames());
    }
}
