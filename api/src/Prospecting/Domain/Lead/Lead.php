<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

use App\Prospecting\Domain\Lead\Event\LeadContacted;
use App\Prospecting\Domain\Lead\Event\LeadCreated;
use App\Prospecting\Domain\Lead\Exception\IllegalStatusTransition;
use App\Shared\Domain\AggregateRoot;
use App\Shared\Domain\ValueObject\Segment;
use App\Shared\Domain\ValueObject\TenantId;

/**
 * Piste de prospection — agrégat racine du contexte Prospecting.
 *
 * Domaine pur : le temps est injecté (paramètre) pour rester déterministe et testable.
 * Les Relances vivent dans cet agrégat (à ajouter) ; les Interactions sont un
 * journal séparé alimenté par les domain events (cf. DOMAIN-MODEL.md).
 */
final class Lead extends AggregateRoot
{
    private function __construct(
        private readonly LeadId $id,
        private readonly TenantId $tenantId,
        private readonly string $organizationId,
        private readonly Segment $segment,
        private PipelineStatus $status,
    ) {
    }

    public static function create(
        LeadId $id,
        TenantId $tenantId,
        string $organizationId,
        Segment $segment,
        \DateTimeImmutable $now,
    ): self {
        $lead = new self($id, $tenantId, $organizationId, $segment, PipelineStatus::TO_CONTACT);
        $lead->recordEvent(new LeadCreated($id->toString(), $now));

        return $lead;
    }

    public function contact(\DateTimeImmutable $now): void
    {
        $this->transitionTo(PipelineStatus::CONTACTED);
        $this->recordEvent(new LeadContacted($this->id->toString(), $now));
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

    public function segment(): Segment
    {
        return $this->segment;
    }

    public function status(): PipelineStatus
    {
        return $this->status;
    }
}
