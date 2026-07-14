<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Domain;

use App\Mailbox\Domain\Outbound\Event\EmailSendRequested;
use App\Mailbox\Domain\Outbound\Event\EmailSent;
use App\Mailbox\Domain\Outbound\Exception\OutboundMessageNotSending;
use App\Mailbox\Domain\Outbound\OutboundMessage;
use App\Mailbox\Domain\Outbound\OutboundMessageId;
use App\Mailbox\Domain\Outbound\OutboundStatus;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class OutboundMessageTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-07-14 17:00:00');
    }

    private function aMessage(): OutboundMessage
    {
        return OutboundMessage::request(
            OutboundMessageId::fromString('out-1'),
            TenantId::fromString('tenant-1'),
            'lead-1',
            'draft-1',
            'APPLICATION_EMAIL',
            EmailAddress::fromString('jeanne@editions.example'),
            $this->now,
        );
    }

    public function testRequestStartsSendingWithAsyncEvent(): void
    {
        $message = $this->aMessage();

        self::assertSame(OutboundStatus::SENDING, $message->status());
        $events = $message->pullDomainEvents();
        self::assertInstanceOf(EmailSendRequested::class, $events[0]);
        self::assertSame('lead-1', $events[0]->leadId);
    }

    public function testMarkSentKeepsThreadKeyForReplyCapture(): void
    {
        $message = $this->aMessage();
        $message->pullDomainEvents();

        $message->markSent('thread-42', $this->now);

        self::assertSame(OutboundStatus::SENT, $message->status());
        self::assertSame('thread-42', $message->threadKey());
        $events = $message->pullDomainEvents();
        self::assertInstanceOf(EmailSent::class, $events[0]);
        self::assertSame('APPLICATION_EMAIL', $events[0]->draftType);
    }

    public function testRedeliveryNeverDoubleCountsASentMessage(): void
    {
        // Messenger livre at-least-once : la seconde livraison arrive sur un SENT.
        $message = $this->aMessage();
        $message->markSent('thread-42', $this->now);

        $this->expectException(OutboundMessageNotSending::class);
        $message->markSent('thread-43', $this->now);
    }

    public function testLateFailureNeverDowngradesASentMessage(): void
    {
        $message = $this->aMessage();
        $message->markSent('thread-42', $this->now);

        $this->expectException(OutboundMessageNotSending::class);
        $message->markFailed('send_failed', $this->now);
    }
}
