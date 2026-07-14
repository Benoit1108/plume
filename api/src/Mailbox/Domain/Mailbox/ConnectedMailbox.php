<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Mailbox;

use App\Mailbox\Domain\Mailbox\Event\MailboxConnected;
use App\Mailbox\Domain\Mailbox\Event\MailboxRevoked;
use App\Mailbox\Domain\Mailbox\Event\MailboxSyncFailed;
use App\Mailbox\Domain\Mailbox\Exception\MailboxNotOperational;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Boîte email connectée (OAuth) — agrégat racine du contexte Mailbox.
 *
 * Identité PROPRE (MailboxId) : « une seule boîte par tenant » est un invariant
 * V1 tenu par le handler + un index unique, PAS une hypothèse structurelle —
 * le multi-boîtes (décision D6) se lèvera sans toucher au modèle.
 * Les tokens n'existent ici que CHIFFRÉS (ADR-0016) ; la révocation les efface.
 */
final class ConnectedMailbox extends AggregateRoot
{
    private function __construct(
        private readonly MailboxId $id,
        private readonly TenantId $tenantId,
        private readonly MailProviderName $provider,
        private EmailAddress $emailAddress,
        private ?EncryptedToken $accessToken,
        private ?EncryptedToken $refreshToken,
        private MailboxStatus $status,
        private ?string $failureReason,
        private readonly \DateTimeImmutable $connectedAt,
        private ?\DateTimeImmutable $lastSyncAt,
        private ?string $syncCursor,
    ) {
    }

    public static function connect(
        MailboxId $id,
        TenantId $tenantId,
        MailProviderName $provider,
        EmailAddress $emailAddress,
        EncryptedToken $accessToken,
        EncryptedToken $refreshToken,
        \DateTimeImmutable $now,
    ): self {
        $mailbox = new self(
            $id,
            $tenantId,
            $provider,
            $emailAddress,
            $accessToken,
            $refreshToken,
            MailboxStatus::CONNECTED,
            null,
            $now,
            null,
            null,
        );
        $mailbox->recordEvent(new MailboxConnected($tenantId->toString(), $id->toString(), $provider->value, $now));

        return $mailbox;
    }

    /** Reconnexion (nouveau consentement) : nouveaux tokens, la boîte redevient opérationnelle. */
    public function reconnect(EmailAddress $emailAddress, EncryptedToken $accessToken, EncryptedToken $refreshToken, \DateTimeImmutable $now): void
    {
        $this->emailAddress = $emailAddress;
        $this->accessToken = $accessToken;
        $this->refreshToken = $refreshToken;
        $this->status = MailboxStatus::CONNECTED;
        $this->failureReason = null;
        $this->recordEvent(new MailboxConnected($this->tenantId->toString(), $this->id->toString(), $this->provider->value, $now));
    }

    /** Rotation silencieuse des tokens (refresh OAuth automatique) — pas un événement métier. */
    public function rotateTokens(EncryptedToken $accessToken, ?EncryptedToken $refreshToken): void
    {
        $this->guardOperational();
        $this->accessToken = $accessToken;
        if (null !== $refreshToken) {
            $this->refreshToken = $refreshToken;
        }
    }

    public function markSyncSucceeded(?string $cursor, \DateTimeImmutable $now): void
    {
        $this->guardOperational();
        $this->syncCursor = $cursor;
        $this->lastSyncAt = $now;
    }

    /** @param string $reason code stable (i18n front) */
    public function markSyncFailed(string $reason, \DateTimeImmutable $now): void
    {
        $this->status = MailboxStatus::ERROR;
        $this->failureReason = $reason;
        $this->recordEvent(new MailboxSyncFailed($this->tenantId->toString(), $this->id->toString(), $reason, $now));
    }

    /** Déconnexion volontaire : les tokens sont EFFACÉS, pas seulement invalidés. */
    public function revoke(\DateTimeImmutable $now): void
    {
        $this->accessToken = null;
        $this->refreshToken = null;
        $this->status = MailboxStatus::REVOKED;
        $this->failureReason = null;
        $this->recordEvent(new MailboxRevoked($this->tenantId->toString(), $this->id->toString(), $now));
    }

    private function guardOperational(): void
    {
        if (MailboxStatus::CONNECTED !== $this->status) {
            throw MailboxNotOperational::inStatus($this->status);
        }
    }

    public function id(): MailboxId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function provider(): MailProviderName
    {
        return $this->provider;
    }

    public function emailAddress(): EmailAddress
    {
        return $this->emailAddress;
    }

    public function accessToken(): ?EncryptedToken
    {
        return $this->accessToken;
    }

    public function refreshToken(): ?EncryptedToken
    {
        return $this->refreshToken;
    }

    public function status(): MailboxStatus
    {
        return $this->status;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function connectedAt(): \DateTimeImmutable
    {
        return $this->connectedAt;
    }

    public function lastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function syncCursor(): ?string
    {
        return $this->syncCursor;
    }
}
