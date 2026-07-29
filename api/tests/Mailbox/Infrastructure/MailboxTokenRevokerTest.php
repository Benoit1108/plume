<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\Exception\TokenCipherFailure;
use App\Mailbox\Application\MailboxConnector;
use App\Mailbox\Application\MailboxConnectorRegistry;
use App\Mailbox\Application\TokenCipher;
use App\Mailbox\Domain\Mailbox\ConnectedMailbox;
use App\Mailbox\Domain\Mailbox\EncryptedToken;
use App\Mailbox\Domain\Mailbox\MailboxId;
use App\Mailbox\Domain\Mailbox\MailboxRepository;
use App\Mailbox\Domain\Mailbox\MailProviderName;
use App\Mailbox\Infrastructure\Gateway\MailboxTokenRevoker;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\TenantId;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * La révocation OAuth à la purge (clôture V2.0, trou ADR-0025) : on révoque le consentement CÔTÉ
 * FOURNISSEUR avec le token DÉCHIFFRÉ, best-effort (boîte absente / token indéchiffrable → no-op,
 * jamais d'exception qui ferait échouer la purge).
 */
final class MailboxTokenRevokerTest extends TestCase
{
    private const TENANT = '0197b7e2-0000-7000-8000-000000000001';

    private function mailboxWithRefreshToken(): ConnectedMailbox
    {
        return ConnectedMailbox::connect(
            MailboxId::fromString('mbx-1'),
            TenantId::fromString(self::TENANT),
            MailProviderName::GMAIL,
            EmailAddress::fromString('marie@plume.test'),
            EncryptedToken::fromCiphertext('cipher-access'),
            EncryptedToken::fromCiphertext('cipher-refresh'),
            new \DateTimeImmutable('2026-07-29 10:00:00'),
        );
    }

    public function testRevokesProviderGrantWithDecryptedRefreshToken(): void
    {
        $repo = $this->createStub(MailboxRepository::class);
        $repo->method('findForTenant')->willReturn($this->mailboxWithRefreshToken());

        $cipher = $this->createStub(TokenCipher::class);
        $cipher->method('decrypt')->willReturn('plain-refresh');

        $connector = $this->createMock(MailboxConnector::class);
        $connector->expects(self::once())->method('revoke')->with('plain-refresh');

        $registry = $this->createMock(MailboxConnectorRegistry::class);
        $registry->expects(self::once())->method('connectorFor')->with('GMAIL')->willReturn($connector);

        (new MailboxTokenRevoker($repo, $registry, $cipher, new NullLogger()))->revokeForTenant(self::TENANT);
    }

    public function testNoOpWhenNoMailboxConnected(): void
    {
        $repo = $this->createStub(MailboxRepository::class);
        $repo->method('findForTenant')->willReturn(null);

        $registry = $this->createMock(MailboxConnectorRegistry::class);
        $registry->expects(self::never())->method('connectorFor');

        (new MailboxTokenRevoker($repo, $registry, $this->createStub(TokenCipher::class), new NullLogger()))
            ->revokeForTenant(self::TENANT);
    }

    public function testNoOpWhenTokenUndecryptable(): void
    {
        $repo = $this->createStub(MailboxRepository::class);
        $repo->method('findForTenant')->willReturn($this->mailboxWithRefreshToken());

        $cipher = $this->createStub(TokenCipher::class);
        $cipher->method('decrypt')->willThrowException(TokenCipherFailure::because('key rotated'));

        $registry = $this->createMock(MailboxConnectorRegistry::class);
        $registry->expects(self::never())->method('connectorFor');

        // Ne jette pas : la purge doit continuer même si le token est indéchiffrable.
        (new MailboxTokenRevoker($repo, $registry, $cipher, new NullLogger()))->revokeForTenant(self::TENANT);
    }
}
