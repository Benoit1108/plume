<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft;

use App\Drafting\Domain\Draft\Event\DraftDeleted;
use App\Drafting\Domain\Draft\Event\DraftEdited;
use App\Drafting\Domain\Draft\Event\DraftFailed;
use App\Drafting\Domain\Draft\Event\DraftGenerated;
use App\Drafting\Domain\Draft\Event\DraftRequested;
use App\Drafting\Domain\Draft\Exception\DraftNotEditable;
use App\Drafting\Domain\Draft\Exception\DraftNotGenerating;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguageCode;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Brouillon — agrégat racine du contexte Drafting (Rédaction assistée).
 *
 * DRAFT-FIRST : un brouillon se génère, se relit, s'édite — il ne s'envoie
 * jamais depuis ce contexte (l'envoi arrive en M2, contexte Mailbox).
 * Génération asynchrone : GENERATING → READY (complete) ou FAILED (fail),
 * puis regenerate() peut relancer un cycle. Référence à la Piste par ID.
 */
final class Draft extends AggregateRoot
{
    private function __construct(
        private readonly DraftId $id,
        private readonly TenantId $tenantId,
        private readonly string $leadId,
        private readonly DraftType $type,
        private readonly LanguageCode $targetLanguage,
        private ?string $templateId,
        private ?string $subject,
        private string $body,
        private DraftStatus $status,
        private ?string $failureReason,
        private readonly \DateTimeImmutable $createdAt,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    public static function request(
        DraftId $id,
        TenantId $tenantId,
        string $leadId,
        DraftType $type,
        LanguageCode $targetLanguage,
        ?string $templateId,
        \DateTimeImmutable $now,
    ): self {
        if ('' === trim($leadId)) {
            throw InvalidValue::because('A draft requires a lead.');
        }

        $draft = new self(
            $id,
            $tenantId,
            $leadId,
            $type,
            $targetLanguage,
            $templateId,
            null,
            '',
            DraftStatus::GENERATING,
            null,
            $now,
            $now,
        );
        $draft->recordEvent(new DraftRequested(
            $tenantId->toString(),
            $id->toString(),
            $leadId,
            $type->value,
            $targetLanguage->toString(),
            $templateId,
            $now,
        ));

        return $draft;
    }

    /** Le générateur a répondu : le brouillon devient éditable. */
    public function complete(?string $subject, string $body, \DateTimeImmutable $now): void
    {
        // Garde d'état : Messenger livre at-least-once — une redélivrance ne doit
        // ni écraser un brouillon déjà relu, ni dupliquer l'entrée du journal.
        if (DraftStatus::GENERATING !== $this->status) {
            throw DraftNotGenerating::inStatus($this->status);
        }
        if ('' === trim($body)) {
            throw InvalidValue::because('A generated draft cannot be empty.');
        }

        $this->subject = $subject;
        $this->body = $body;
        $this->status = DraftStatus::READY;
        $this->failureReason = null;
        $this->updatedAt = $now;
        $this->recordEvent(new DraftGenerated($this->tenantId->toString(), $this->id->toString(), $this->leadId, $this->type->value, $now));
    }

    /** Échec de génération : raison AFFICHABLE (jamais un message interne). */
    public function fail(string $reason, \DateTimeImmutable $now): void
    {
        // Même garde : un échec retardataire ne rétrograde jamais un brouillon READY.
        if (DraftStatus::GENERATING !== $this->status) {
            throw DraftNotGenerating::inStatus($this->status);
        }
        $this->status = DraftStatus::FAILED;
        $this->failureReason = $reason;
        $this->updatedAt = $now;
        $this->recordEvent(new DraftFailed($this->tenantId->toString(), $this->id->toString(), $reason, $now));
    }

    /** Relecture humaine : édition libre du sujet et du corps (READY uniquement). */
    public function edit(?string $subject, string $body, \DateTimeImmutable $now): void
    {
        if (DraftStatus::READY !== $this->status) {
            throw DraftNotEditable::inStatus($this->status);
        }
        if ('' === trim($body)) {
            throw InvalidValue::because('A draft body cannot be empty.');
        }

        $this->subject = $subject;
        $this->body = $body;
        $this->updatedAt = $now;
        $this->recordEvent(new DraftEdited($this->tenantId->toString(), $this->id->toString(), $now));
    }

    /** Nouveau cycle de génération (le contenu courant sera remplacé). */
    public function regenerate(\DateTimeImmutable $now): void
    {
        if (DraftStatus::GENERATING === $this->status) {
            throw DraftNotEditable::inStatus($this->status);
        }

        $this->status = DraftStatus::GENERATING;
        $this->failureReason = null;
        $this->updatedAt = $now;
        $this->recordEvent(new DraftRequested(
            $this->tenantId->toString(),
            $this->id->toString(),
            $this->leadId,
            $this->type->value,
            $this->targetLanguage->toString(),
            $this->templateId,
            $now,
        ));
    }

    /** Trace la suppression (le retrait effectif est fait par le repository). */
    public function delete(\DateTimeImmutable $now): void
    {
        $this->recordEvent(new DraftDeleted($this->tenantId->toString(), $this->id->toString(), $now));
    }

    public function id(): DraftId
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

    public function type(): DraftType
    {
        return $this->type;
    }

    public function targetLanguage(): LanguageCode
    {
        return $this->targetLanguage;
    }

    public function templateId(): ?string
    {
        return $this->templateId;
    }

    public function subject(): ?string
    {
        return $this->subject;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function status(): DraftStatus
    {
        return $this->status;
    }

    public function failureReason(): ?string
    {
        return $this->failureReason;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
