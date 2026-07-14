<?php

declare(strict_types=1);

namespace App\Prospecting\Infrastructure\Projection;

use App\Drafting\Domain\Draft\Event\DraftGenerated;
use App\Prospecting\Domain\Lead\Event\FollowUpCancelled;
use App\Prospecting\Domain\Lead\Event\FollowUpScheduled;
use App\Prospecting\Domain\Lead\Event\FollowUpSent;
use App\Prospecting\Domain\Lead\Event\LeadContacted;
use App\Prospecting\Domain\Lead\Event\LeadCreated;
use App\Prospecting\Domain\Lead\Event\LeadLost;
use App\Prospecting\Domain\Lead\Event\LeadMovedToSampleTest;
use App\Prospecting\Domain\Lead\Event\LeadPaused;
use App\Prospecting\Domain\Lead\Event\LeadResumed;
use App\Prospecting\Domain\Lead\Event\LeadWon;
use App\Prospecting\Domain\Lead\Event\NoteAdded;
use App\Prospecting\Domain\Lead\Event\ReplyReceived;
use App\Shared\Domain\DomainEvent;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Projette les events Lead dans le journal `interaction` (timeline append-only).
 * Consommé en ASYNCHRONE par le worker (outbox) — idempotent : la contrainte
 * unique sur event_id absorbe les retries Messenger (ON CONFLICT DO NOTHING).
 * Les events sont riches (tenant + données) : aucune relecture d'agrégat.
 */
final class InteractionProjector
{
    public function __construct(private readonly Connection $connection)
    {
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onLeadCreated(LeadCreated $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'created', ['organizationId' => $event->organizationId]);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onLeadContacted(LeadContacted $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'contacted');
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onReplyReceived(ReplyReceived $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'reply');
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onMovedToSampleTest(LeadMovedToSampleTest $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'sample_test');
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onLeadWon(LeadWon $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'won');
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onLeadLost(LeadLost $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'lost');
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onLeadPaused(LeadPaused $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'paused', ['from' => $event->pausedFrom]);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onLeadResumed(LeadResumed $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'resumed', ['to' => $event->resumedTo]);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onNoteAdded(NoteAdded $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'note', ['text' => $event->text]);
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onFollowUpScheduled(FollowUpScheduled $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'follow_up_scheduled', ['dueAt' => $event->dueAt, 'label' => $event->label, 'auto' => $event->auto]);
    }

    /** Type `followed_up` : compte comme acte de démarchage (progression hebdo). */
    #[AsMessageHandler(bus: 'event.bus')]
    public function onFollowUpSent(FollowUpSent $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'followed_up');
    }

    #[AsMessageHandler(bus: 'event.bus')]
    public function onFollowUpCancelled(FollowUpCancelled $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'follow_up_cancelled', ['reason' => $event->reason]);
    }

    /** Event du contexte Drafting — le journal de la piste agrège tous les contextes. */
    #[AsMessageHandler(bus: 'event.bus')]
    public function onDraftGenerated(DraftGenerated $event): void
    {
        $this->record($event, $event->tenantId, $event->leadId, 'draft_generated', ['draftType' => $event->type]);
    }

    /** @param array<string, mixed> $payload */
    private function record(DomainEvent $event, string $tenantId, string $leadId, string $type, array $payload = []): void
    {
        $this->connection->executeStatement(
            'INSERT INTO interaction (id, event_id, tenant_id, lead_id, type, payload, occurred_on)
             VALUES (:id, :event_id, :tenant_id, :lead_id, :type, :payload, :occurred_on)
             ON CONFLICT (event_id) DO NOTHING',
            [
                'id' => Uuid::v7()->toRfc4122(),
                'event_id' => $event->eventId(),
                'tenant_id' => $tenantId,
                'lead_id' => $leadId,
                'type' => $type,
                'payload' => json_encode($payload, \JSON_THROW_ON_ERROR),
                'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s.u'),
            ],
        );
    }
}
