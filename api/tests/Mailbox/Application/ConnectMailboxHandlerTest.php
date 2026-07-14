<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Application;

use App\Mailbox\Application\Command\ConnectMailbox\ConnectMailbox;
use App\Mailbox\Application\Command\ConnectMailbox\ConnectMailboxHandler;
use App\Mailbox\Application\Command\RevokeMailbox\RevokeMailbox;
use App\Mailbox\Application\Command\RevokeMailbox\RevokeMailboxHandler;
use App\Mailbox\Domain\Mailbox\Event\MailboxConnected;
use App\Mailbox\Domain\Mailbox\Event\MailboxRevoked;
use App\Mailbox\Domain\Mailbox\Exception\MailboxNotFound;
use App\Mailbox\Domain\Mailbox\MailboxStatus;
use App\Mailbox\Infrastructure\OAuth\FakeMailboxConnector;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\TenantId;
use App\Tests\Support\FakeTokenCipher;
use App\Tests\Support\FixedClock;
use App\Tests\Support\InMemoryMailboxRepository;
use App\Tests\Support\RecordingEventBus;
use App\Tests\Support\SingleMailboxConnectorRegistry;
use PHPUnit\Framework\TestCase;

final class ConnectMailboxHandlerTest extends TestCase
{
    private const string TENANT = '0197b7e2-0000-7000-8000-000000000001';

    private InMemoryMailboxRepository $mailboxes;
    private RecordingEventBus $eventBus;
    private ConnectMailboxHandler $connect;
    private RevokeMailboxHandler $revoke;

    protected function setUp(): void
    {
        $this->mailboxes = new InMemoryMailboxRepository();
        $this->eventBus = new RecordingEventBus();
        $connectors = new SingleMailboxConnectorRegistry(new FakeMailboxConnector('http://localhost:3000/oauth/gmail/callback'));
        $cipher = new FakeTokenCipher();
        $clock = new FixedClock(new \DateTimeImmutable('2026-07-14 15:00:00'));
        $this->connect = new ConnectMailboxHandler($this->mailboxes, $connectors, $cipher, $this->eventBus, $clock);
        $this->revoke = new RevokeMailboxHandler($this->mailboxes, $connectors, $cipher, $this->eventBus, $clock);
    }

    public function testConnectStoresOnlyEncryptedTokens(): void
    {
        ($this->connect)(new ConnectMailbox('mb-1', self::TENANT, 'GMAIL', FakeMailboxConnector::ACCEPTED_CODE));

        $mailbox = $this->mailboxes->findForTenant(TenantId::fromString(self::TENANT));
        self::assertNotNull($mailbox);
        self::assertSame(MailboxStatus::CONNECTED, $mailbox->status());
        self::assertSame('traductrice@gmail.example', $mailbox->emailAddress()->toString());
        // Jamais de clair en base : le ciphertext ne contient pas le token.
        self::assertSame('enc(fake-access-token)', $mailbox->accessToken()?->ciphertext());
        self::assertSame(1, $this->eventBus->countOf(MailboxConnected::class));
    }

    public function testSecondConnectIsAReconnection(): void
    {
        ($this->connect)(new ConnectMailbox('mb-1', self::TENANT, 'GMAIL', FakeMailboxConnector::ACCEPTED_CODE));
        ($this->connect)(new ConnectMailbox('mb-2', self::TENANT, 'GMAIL', FakeMailboxConnector::ACCEPTED_CODE));

        // Une seule boîte par tenant (V1) : même agrégat, identité d'origine conservée.
        $mailbox = $this->mailboxes->findForTenant(TenantId::fromString(self::TENANT));
        self::assertSame('mb-1', $mailbox?->id()->toString());
        self::assertSame(2, $this->eventBus->countOf(MailboxConnected::class));
    }

    public function testUnknownProviderIsRejected(): void
    {
        $this->expectException(InvalidValue::class);
        ($this->connect)(new ConnectMailbox('mb-1', self::TENANT, 'PIGEON', FakeMailboxConnector::ACCEPTED_CODE));
    }

    public function testRevokeErasesTokensAndPublishes(): void
    {
        ($this->connect)(new ConnectMailbox('mb-1', self::TENANT, 'GMAIL', FakeMailboxConnector::ACCEPTED_CODE));

        ($this->revoke)(new RevokeMailbox(self::TENANT));

        $mailbox = $this->mailboxes->findForTenant(TenantId::fromString(self::TENANT));
        self::assertSame(MailboxStatus::REVOKED, $mailbox?->status());
        self::assertNull($mailbox->accessToken());
        self::assertSame(1, $this->eventBus->countOf(MailboxRevoked::class));
    }

    public function testRevokeWithoutMailboxIsNotFound(): void
    {
        $this->expectException(MailboxNotFound::class);
        ($this->revoke)(new RevokeMailbox(self::TENANT));
    }
}
