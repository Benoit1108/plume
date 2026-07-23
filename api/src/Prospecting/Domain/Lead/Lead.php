<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

use App\Prospecting\Domain\Lead\Event\FollowUpCancelled;
use App\Prospecting\Domain\Lead\Event\FollowUpScheduled;
use App\Prospecting\Domain\Lead\Event\FollowUpSent;
use App\Prospecting\Domain\Lead\Event\LeadContacted;
use App\Prospecting\Domain\Lead\Event\LeadCreated;
use App\Prospecting\Domain\Lead\Event\LeadLost;
use App\Prospecting\Domain\Lead\Event\LeadMovedToSampleTest;
use App\Prospecting\Domain\Lead\Event\LeadPaused;
use App\Prospecting\Domain\Lead\Event\LeadResumed;
use App\Prospecting\Domain\Lead\Event\LeadReturnedToContact;
use App\Prospecting\Domain\Lead\Event\LeadWon;
use App\Prospecting\Domain\Lead\Event\NoteAdded;
use App\Prospecting\Domain\Lead\Event\ReplyReceived;
use App\Prospecting\Domain\Lead\Exception\FollowUpNotAllowed;
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
 * Les Relances (`FollowUp`) vivent DANS l'agrégat (collection JSONB) avec une
 * dénormalisation `nextFollowUpAt` pour la requête « dues aujourd'hui » ;
 * les Interactions sont un journal séparé alimenté par les domain events
 * (cf. DOMAIN-MODEL.md, ADR-0003). Références inter-contextes par ID uniquement.
 */
final class Lead extends AggregateRoot
{
    /** @param FollowUp[] $followUps */
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
        private array $followUps,
        private ?\DateTimeImmutable $nextFollowUpAt,
        private ?string $nextFollowUpLabel,
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
            [],
            null,
            null,
        );
        $lead->recordEvent(new LeadCreated($tenantId->toString(), $id->toString(), $organizationId, $now));

        return $lead;
    }

    /** Contact établi : la cadence démarre (1ʳᵉ relance auto-planifiée à J+7). */
    public function contact(\DateTimeImmutable $now): void
    {
        $this->transitionTo(PipelineStatus::CONTACTED);
        $this->lastContactedAt = $now;
        $this->recordEvent(new LeadContacted($this->tenantId->toString(), $this->id->toString(), $now));
        $this->autoScheduleFollowUp($now);
    }

    /**
     * Correction d'un « Contacter » cliqué par erreur : retour à « À contacter ». On annule la
     * relance auto planifiée et on efface la date de contact (la piste n'a jamais été contactée).
     */
    public function returnToContact(\DateTimeImmutable $now): void
    {
        $this->transitionTo(PipelineStatus::TO_CONTACT);
        $this->cancelPendingFollowUp(FollowUpCancelReason::MANUAL, $now);
        $this->lastContactedAt = null;
        $this->recordEvent(new LeadReturnedToContact($this->tenantId->toString(), $this->id->toString(), $now));
    }

    /** Réponse entrante : la discussion s'ouvre, la relance en attente est annulée. */
    /**
     * IDEMPOTENT (dette revue fin M1, soldée en M2.3) : les réponses captées
     * automatiquement peuvent arriver plusieurs fois — une piste déjà en
     * discussion n'a plus rien à faire d'une réponse de plus (no-op, sans event).
     */
    public function recordReply(\DateTimeImmutable $now, ?string $preview = null): void
    {
        if (PipelineStatus::IN_DISCUSSION === $this->status) {
            return;
        }
        $this->transitionTo(PipelineStatus::IN_DISCUSSION);
        $this->lastReplyAt = $now;
        $this->recordEvent(new ReplyReceived($this->tenantId->toString(), $this->id->toString(), $preview, $now));
        $this->cancelPendingFollowUp(FollowUpCancelReason::REPLY, $now);
    }

    /** Relance faite (acte manuel en M1 — l'envoi réel arrive en M2). Cadence : suivante auto. */
    public function recordFollowUp(\DateTimeImmutable $now): void
    {
        $this->transitionTo(PipelineStatus::FOLLOWED_UP);

        $pending = $this->pendingFollowUp();
        if (null !== $pending) {
            $this->replaceFollowUp($pending->done());
            $followUpId = $pending->id();
        } else {
            // Relance faite sans planification préalable : on la consigne (cadence + journal).
            $done = new FollowUp(FollowUpId::generate(), $now, null, FollowUpStatus::DONE);
            $this->followUps[] = $done;
            $followUpId = $done->id();
        }

        $this->syncNextFollowUp();
        $this->recordEvent(new FollowUpSent($this->tenantId->toString(), $this->id->toString(), $followUpId->toString(), $now));
        $this->autoScheduleFollowUp($now);
    }

    /** Planification (ou replanification) manuelle — remplace la relance en attente. */
    public function scheduleFollowUp(\DateTimeImmutable $dueAt, ?string $label, \DateTimeImmutable $now): void
    {
        $this->guardFollowUpAllowed();
        if ($dueAt->format('Y-m-d') < $now->format('Y-m-d')) {
            throw InvalidValue::because('A follow-up cannot be scheduled in the past.');
        }

        $pending = $this->pendingFollowUp();
        if (null !== $pending) {
            // Remplacement silencieux : le fait métier est la nouvelle planification.
            $this->followUps = array_values(array_filter(
                $this->followUps,
                static fn (FollowUp $followUp): bool => !$followUp->id()->equals($pending->id()),
            ));
        }

        $this->applySchedule($dueAt, null !== $label && '' !== trim($label) ? trim($label) : null, auto: false, now: $now);
    }

    /** Annulation volontaire de la relance en attente (sans effet s'il n'y en a pas). */
    public function cancelFollowUp(\DateTimeImmutable $now): void
    {
        $this->cancelPendingFollowUp(FollowUpCancelReason::MANUAL, $now);
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
        $this->cancelPendingFollowUp(FollowUpCancelReason::TERMINAL, $now);
    }

    public function markLost(\DateTimeImmutable $now): void
    {
        $this->transitionTo(PipelineStatus::LOST);
        $this->recordEvent(new LeadLost($this->tenantId->toString(), $this->id->toString(), $now));
        $this->cancelPendingFollowUp(FollowUpCancelReason::TERMINAL, $now);
    }

    /** Mise en pause : mémorise le statut courant, annule la relance en attente. */
    public function pause(\DateTimeImmutable $now): void
    {
        $from = $this->status;
        $this->transitionTo(PipelineStatus::PAUSED);
        $this->statusBeforePause = $from;
        $this->recordEvent(new LeadPaused($this->tenantId->toString(), $this->id->toString(), $from->value, $now));
        $this->cancelPendingFollowUp(FollowUpCancelReason::PAUSED, $now);
    }

    /** Reprise au statut mémorisé — sans replanification automatique (l'utilisatrice décide). */
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

    // ----- Relances : mécanique interne -----

    /** Cadence par défaut : J+7 après contact, puis J+21, J+45 après chaque relance faite. */
    private function autoScheduleFollowUp(\DateTimeImmutable $now): void
    {
        $delay = FollowUpCadence::nextDelayInDays($this->doneFollowUpsCount());
        if (null === $delay || null !== $this->pendingFollowUp()) {
            return;
        }

        $this->applySchedule($now->modify(sprintf('+%d days', $delay)), null, auto: true, now: $now);
    }

    private function applySchedule(\DateTimeImmutable $dueAt, ?string $label, bool $auto, \DateTimeImmutable $now): void
    {
        $followUp = FollowUp::pending(FollowUpId::generate(), $dueAt, $label);
        $this->followUps[] = $followUp;
        $this->syncNextFollowUp();
        $this->recordEvent(new FollowUpScheduled(
            $this->tenantId->toString(),
            $this->id->toString(),
            $followUp->id()->toString(),
            $dueAt->format('Y-m-d'),
            $label,
            $auto,
            $now,
        ));
    }

    private function cancelPendingFollowUp(FollowUpCancelReason $reason, \DateTimeImmutable $now): void
    {
        $pending = $this->pendingFollowUp();
        if (null === $pending) {
            return;
        }

        $this->replaceFollowUp($pending->cancelled());
        $this->syncNextFollowUp();
        $this->recordEvent(new FollowUpCancelled(
            $this->tenantId->toString(),
            $this->id->toString(),
            $pending->id()->toString(),
            $reason->value,
            $now,
        ));
    }

    private function guardFollowUpAllowed(): void
    {
        if ($this->status->isTerminal() || PipelineStatus::PAUSED === $this->status) {
            throw FollowUpNotAllowed::inStatus($this->status);
        }
    }

    private function pendingFollowUp(): ?FollowUp
    {
        foreach ($this->followUps as $followUp) {
            if ($followUp->isPending()) {
                return $followUp;
            }
        }

        return null;
    }

    private function doneFollowUpsCount(): int
    {
        return \count(array_filter($this->followUps, static fn (FollowUp $followUp): bool => FollowUpStatus::DONE === $followUp->status()));
    }

    /** Remplace l'instance (réf. différente) pour que Doctrine détecte la mutation JSON. */
    private function replaceFollowUp(FollowUp $replacement): void
    {
        $this->followUps = array_map(
            static fn (FollowUp $followUp): FollowUp => $followUp->id()->equals($replacement->id()) ? $replacement : $followUp,
            $this->followUps,
        );
    }

    /** Maintient la dénormalisation servant la requête « dues aujourd'hui ». */
    private function syncNextFollowUp(): void
    {
        $pending = $this->pendingFollowUp();
        $this->nextFollowUpAt = $pending?->dueAt();
        $this->nextFollowUpLabel = $pending?->label();
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

    /** @return FollowUp[] */
    public function followUps(): array
    {
        return $this->followUps;
    }

    public function nextFollowUpAt(): ?\DateTimeImmutable
    {
        return $this->nextFollowUpAt;
    }

    public function nextFollowUpLabel(): ?string
    {
        return $this->nextFollowUpLabel;
    }
}
