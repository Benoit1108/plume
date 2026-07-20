<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Infrastructure;

use App\Sourcing\Application\Command\PollAlertSource\PollAlertSource;
use App\Sourcing\Infrastructure\Scheduler\PollAllSourcesHandler;
use App\Sourcing\Infrastructure\Scheduler\PollAllSourcesTick;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class PollAllSourcesHandlerTest extends TestCase
{
    public function testDispatchesOneAsyncPollPerTenant(): void
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

        (new PollAllSourcesHandler($connection, $bus))(new PollAllSourcesTick());

        self::assertCount(2, $dispatched); // isolation : un message par tenant
        $first = $dispatched[0]['msg'];
        $second = $dispatched[1]['msg'];
        self::assertInstanceOf(PollAlertSource::class, $first);
        self::assertInstanceOf(PollAlertSource::class, $second);
        self::assertSame('tenant-a', $first->tenantId);
        self::assertSame('tenant-b', $second->tenantId);

        // Chaque poll part sur le transport ASYNCHRONE (pas d'imbrication synchrone).
        $stamp = $dispatched[0]['stamps'][0];
        self::assertInstanceOf(TransportNamesStamp::class, $stamp);
        self::assertSame(['async'], $stamp->getTransportNames());
    }
}
