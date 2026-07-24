<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\Command\FetchAlertEmails\FetchAlertEmails;
use App\Mailbox\Infrastructure\Scheduler\FetchAllAlertEmailsHandler;
use App\Mailbox\Infrastructure\Scheduler\FetchAllAlertEmailsTick;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

final class FetchAllAlertEmailsHandlerTest extends TestCase
{
    public function testDispatchesOneAsyncFetchPerConnectedMailbox(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(['tenant-a']);

        /** @var list<array{msg: object, stamps: list<object>}> $dispatched */
        $dispatched = [];
        $bus = $this->createStub(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function (object $msg, array $stamps = []) use (&$dispatched): Envelope {
            $dispatched[] = ['msg' => $msg, 'stamps' => $stamps];

            return new Envelope($msg);
        });

        (new FetchAllAlertEmailsHandler($connection, $bus))(new FetchAllAlertEmailsTick());

        self::assertCount(1, $dispatched);
        $msg = $dispatched[0]['msg'];
        self::assertInstanceOf(FetchAlertEmails::class, $msg);
        self::assertSame('tenant-a', $msg->tenantId);
        $stamp = $dispatched[0]['stamps'][0];
        self::assertInstanceOf(TransportNamesStamp::class, $stamp);
        self::assertSame(['io'], $stamp->getTransportNames());
    }
}
