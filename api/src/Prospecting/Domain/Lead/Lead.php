<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

use App\Prospecting\Domain\Lead\Event\LeadContacted;
use App\Prospecting\Domain\Lead\Event\LeadCreated;
use App\Prospecting\Domain\Lead\Event\LeadLost;
use App\Prospecting\Domain\Lead\Event\LeadMovedToSampleTest;
use App\Prospecting\Domain\Lead\Event\LeadPaused;
use App\Prospecting\Domain\Lead\Event\LeadResumed;
use App\Prospecting\Domain\Lead\Event\LeadWon;
use App\Prospecting\Domain\Lead\Event\NoteAdded;
use App\Prospecting\Domain\Lead\Event\ReplyReceived;
use App\Prospecting\Domain\Lead\Exception\IllegalStatusTransition;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguagePair;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Piste de prospection — agrégat racine du contexte Prospecting.
 *
 * Domaine pur : le temps est injecté (paramètre) pour rester déterministe et testable.
 * Les Relances (`FollowUp`) arrivent en M1.3 ; les Interactions sont un journal
 * séparé alimenté par les domain events (cf. DOMAIN-MODEL.md, ADR-0003).
 * Références inter-contextes par ID uniquement (organizationId, contactId).
 */
final class Lead extends AggregateRoot
{
    private function __construct(
        private readonly LeadId $id,
        private readonly TenantId $tenantId,
        private readonly string $organizationId,
        private ?string $contactId,
        private LanguagePair $languagePair,
        private readonly LeadSource $source,
        private Priority $priority,
        private readonly Segment $segment,
        private PipelineStatus $status,
        private ?PipelineStatus $statusBeforePause,
        private readonly \DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $lastContactedAt,
        private ?\DateTimeImmutable $lastReplyAt,
    ) {
    }

    public static function create(
        LeadId $id,
        TenantId $tenantId,
        string $organizationId,
        ?string $contactId,
        LanguagePair $languagePair,
        LeadSource $source,
        Priority $priority,
        Segment $segment,
        \DateTimeImmutable $now,
    ): self {
        if ('' === trim($organizationId)) {
            throw InvalidValue::because('A lead requires an organization.');
        }

        $lead = new self(
            $id,
            $tenantId,
            $organizationId,
            $contactId,
            $languagePair,
            $source,
            $priority,
            $segment,
            PipelineStatus::TO_CONTACT,
            null,
            $now,
            null,
            null,
        );
        $lead->recordEvent(new LeadCreated($tenantId->toString(), $id->toString(), $organizationId, $now));

        return $lead;
    }

    public function contact(\DateTimeImmutable $now): void
    {
        $this->transitionTo(PipelineStatus::CONTACTED);
        $this->lastContactedAt = $now;
        $this->recordEvent(new LeadContacted($this->tenantId->toString(), $this->id->toString(), $now));
    }

    /** Réponse entrante : la discussion s'ouvre (annulera les relances en M1.3). */
    public function recordReply(\DateTimeImmutable $now): void
    {
        $this->transitionTo(PipelineStatus::IN_DISCUSSION);
        $this->lastReplyAt = $now;
        $this->recordEvent(new ReplyReceived($this->tenantId->toString(), $this->id->toString(), $now));
    }

    public function moveToSampleTest(\DateTimeImmutable $now): void
    {
        $this->transitionTo(PipelineStatus::SAMPLE_TEST);
        $this->recordEvent(new LeadMovedToSampleTest($this->tenantId->toString(), $this->id->toString(), $now));
    }

    public function markWon(\DateTimeImmutable $now): void
    {
        $this->transitionTo(PipelineStatus::WON);
        $this->recordEvent(new LeadWon($this->tenantId->toString(), $this->id->toString(), $now));
    }

    public function markLost(\DateTimeImmutable $now): void
    {
        $this->transitionTo(PipelineStatus::LOST);
        $this->recordEvent(new LeadLost($this->tenantId->toString(), $this->id->toString(), $now));
    }

    /** Mise en pause : mémorise le statut courant pour reprendre exactement là. */
    public function pause(\DateTimeImmutable $now): void
    {
        $from = $this->status;
        $this->transitionTo(PipelineStatus::PAUSED);
        $this->statusBeforePause = $from;
        $this->recordEvent(new LeadPaused($this->tenantId->toString(), $this->id->toString(), $from->value, $now));
    }

    public function resume(\DateTimeImmutable $now): void
    {
        $target = $this->statusBeforePause
            ?? throw IllegalStatusTransition::between($this->status, PipelineStatus::TO_CONTACT);
        $this->transitionTo($target);
        $this->statusBeforePause = null;
        $this->recordEvent(new LeadResumed($this->tenantId->toString(), $this->id->toString(), $target->value, $now));
    }

    /** La note vit dans l'event (→ journal d'interactions), pas dans l'agrégat. */
    public function addNote(string $text, \DateTimeImmutable $now): void
    {
        $trimmed = trim($text);
        if ('' === $trimmed) {
            throw InvalidValue::because('A note cannot be empty.');
        }

        $this->recordEvent(new NoteAdded($this->tenantId->toString(), $this->id->toString(), $trimmed, $now));
    }

    private function transitionTo(PipelineStatus $target): void
    {
        if (!$this->status->canTransitionTo($target)) {
            throw IllegalStatusTransition::between($this->status, $target);
        }

        $this->status = $target;
    }

    public function id(): LeadId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function organizationId(): string
    {
        return $this->organizationId;
    }

    public function contactId(): ?string
    {
        return $this->contactId;
    }

    public function languagePair(): LanguagePair
    {
        return $this->languagePair;
    }

    public function source(): LeadSource
    {
        return $this->source;
    }

    public function priority(): Priority
    {
        return $this->priority;
    }

    public function segment(): Segment
    {
        return $this->segment;
    }

    public function status(): PipelineStatus
    {
        return $this->status;
    }

    public function statusBeforePause(): ?PipelineStatus
    {
        return $this->statusBeforePause;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function lastContactedAt(): ?\DateTimeImmutable
    {
        return $this->lastContactedAt;
    }

    public function lastReplyAt(): ?\DateTimeImmutable
    {
        return $this->lastReplyAt;
    }
}
