<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Domain;

use App\Mailbox\Domain\Mailbox\ConnectedMailbox;
use App\Mailbox\Domain\Mailbox\EncryptedToken;
use App\Mailbox\Domain\Mailbox\Event\MailboxConnected;
use App\Mailbox\Domain\Mailbox\Event\MailboxRevoked;
use App\Mailbox\Domain\Mailbox\Event\MailboxSyncFailed;
use App\Mailbox\Domain\Mailbox\Exception\MailboxNotOperational;
use App\Mailbox\Domain\Mailbox\MailboxId;
use App\Mailbox\Domain\Mailbox\MailboxStatus;
use App\Mailbox\Domain\Mailbox\MailProviderName;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;

final class ConnectedMailboxTest extends TestCase
{
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->now = new \DateTimeImmutable('2026-07-14 15:00:00');
    }

    private function aMailbox(): ConnectedMailbox
    {
        return ConnectedMailbox::connect(
            MailboxId::fromString('mb-1'),
            TenantId::fromString('tenant-1'),
            MailProviderName::GMAIL,
            EmailAddress::fromString('marie@gmail.example'),
            EncryptedToken::fromCiphertext('enc-access'),
            EncryptedToken::fromCiphertext('enc-refresh'),
            $this->now,
        );
    }

    public function testConnectStartsOperationalWithEvent(): void
    {
        $mailbox = $this->aMailbox();

        self::assertSame(MailboxStatus::CONNECTED, $mailbox->status());
        self::assertSame('marie@gmail.example', $mailbox->emailAddress()->toString());
        $events = $mailbox->pullDomainEvents();
        self::assertCount(1, $events);
        self::assertInstanceOf(MailboxConnected::class, $events[0]);
        self::assertSame('GMAIL', $events[0]->provider);
    }

    public function testRevokeErasesTokens(): void
    {
        $mailbox = $this->aMailbox();
        $mailbox->pullDomainEvents();

        $mailbox->revoke($this->now);

        self::assertSame(MailboxStatus::REVOKED, $mailbox->status());
        self::assertNull($mailbox->accessToken());
        self::assertNull($mailbox->refreshToken());
        self::assertInstanceOf(MailboxRevoked::class, $mailbox->pullDomainEvents()[0]);
    }

    public function testSyncFailureIsDisplayableAndRecoverableByReconnect(): void
    {
        $mailbox = $this->aMailbox();
        $mailbox->pullDomainEvents();

        $mailbox->markSyncFailed('token_expired', $this->now);
        self::assertSame(MailboxStatus::ERROR, $mailbox->status());
        self::assertSame('token_expired', $mailbox->failureReason());
        self::assertInstanceOf(MailboxSyncFailed::class, $mailbox->pullDomainEvents()[0]);

        // Reconnexion : la boîte redevient opérationnelle avec de nouveaux tokens.
        $mailbox->reconnect(
            EmailAddress::fromString('marie@gmail.example'),
            EncryptedToken::fromCiphertext('enc-access-2'),
            EncryptedToken::fromCiphertext('enc-refresh-2'),
            $this->now,
        );
        self::assertSame(MailboxStatus::CONNECTED, $mailbox->status());
        self::assertNull($mailbox->failureReason());
    }

    public function testOperationsAreGuardedOnNonOperationalMailbox(): void
    {
        $mailbox = $this->aMailbox();
        $mailbox->revoke($this->now);

        $this->expectException(MailboxNotOperational::class);
        $mailbox->rotateTokens(EncryptedToken::fromCiphertext('enc-x'), null);
    }

    public function testSyncBookkeeping(): void
    {
        $mailbox = $this->aMailbox();

        $mailbox->markSyncSucceeded('history-42', $this->now);

        self::assertSame('history-42', $mailbox->syncCursor());
        self::assertEquals($this->now, $mailbox->lastSyncAt());
    }
}
