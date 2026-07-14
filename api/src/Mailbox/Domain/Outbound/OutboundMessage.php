<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound;

use App\Mailbox\Domain\Outbound\Event\EmailSendFailed;
use App\Mailbox\Domain\Outbound\Event\EmailSendRequested;
use App\Mailbox\Domain\Outbound\Event\EmailSent;
use App\Mailbox\Domain\Outbound\Exception\OutboundMessageNotSending;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Envoi — agrégat du contexte Mailbox. Trace l'ACTE d'envoi (statut, fil) ;
 * le corps du message vit dans le brouillon et dans la boîte, pas ici.
 * Machine à états gardée (SENDING → SENT | FAILED) : la redélivrance Messenger
 * ne double jamais un envoi comptabilisé (leçon P0 revue fin M1, appliquée d'entrée).
 */
final class OutboundMessage extends AggregateRoot
{
    private function __construct(
        private readonly OutboundMessageId $id,
        private readonly TenantId $tenantId,
        private readonly string $leadId,
        private readonly string $draftId,
        private readonly string $draftType,
        private readonly EmailAddress $recipient,
        private ?string $threadKey,
        private OutboundStatus $status,
        private ?string $failureReason,
        private readonly \DateTimeImmutable $requestedAt,
        private ?\DateTimeImmutable $sentAt,
    ) {
    }

    public static function request(
        OutboundMessageId $id,
        TenantId $tenantId,
        string $leadId,
        string $draftId,
        string $draftType,
        EmailAddress $recipient,
        \DateTimeImmutable $now,
    ): self {
        if ('' === trim($leadId) || '' === trim($draftId)) {
            throw InvalidValue::because('An outbound message requires a lead and a draft.');
        }

        $message = new self($id, $tenantId, $leadId, $draftId, $draftType, $recipient, null, OutboundStatus::SENDING, null, $now, null);
        $message->recordEvent(new EmailSendRequested($tenantId->toString(), $id->toString(), $draftId, $leadId, $now));

        return $message;
    }

    /** Le provider a accepté : le fil (threadKey) permettra de capter les réponses (M2.3). */
    public function markSent(string $threadKey, \DateTimeImmutable $now): void
    {
        if (OutboundStatus::SENDING !== $this->status) {
            throw OutboundMessageNotSending::inStatus($this->status);
        }
        if ('' === trim($threadKey)) {
            throw InvalidValue::because('A sent message requires its thread key.');
        }

        $this->threadKey = $threadKey;
        $this->status = OutboundStatus::SENT;
        $this->sentAt = $now;
        $this->recordEvent(new EmailSent($this->tenantId->toString(), $this->id->toString(), $this->leadId, $this->draftType, $threadKey, $now));
    }

    /** @param string $reason code stable affichable (i18n front) */
    public function markFailed(string $reason, \DateTimeImmutable $now): void
    {
        if (OutboundStatus::SENDING !== $this->status) {
            throw OutboundMessageNotSending::inStatus($this->status);
        }

        $this->status = OutboundStatus::FAILED;
        $this->failureReason = $reason;
        $this->recordEvent(new EmailSendFailed($this->tenantId->toString(), $this->id->toString(), $this->leadId, $reason, $now));
    }

    public function id(): OutboundMessageId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function leadId(): string
    {
        return $this->leadId;
    }

    public function draftId(): string
    {
        return $this->draftId;
    }

    public function draftType(): string
    {
        return $this->draftType;
    }

    public function recipient(): EmailAddress
    {
        return $this->recipient;
    }

    public function threadKey(): ?string
    {
        return $this->threadKey;
    }

    public function status(): OutboundStatus
    {
        return $this->status;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function requestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function sentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }
}
