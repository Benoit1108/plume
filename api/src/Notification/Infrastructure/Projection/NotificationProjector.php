<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Projection;

use App\Mailbox\Domain\Mailbox\Event\MailboxSyncFailed;
use App\Mailbox\Domain\Outbound\Event\EmailSendFailed;
use App\Mailbox\Domain\Outbound\Event\ReplyCaptured;
use App\Shared\Domain\DomainEvent;
use App\Sourcing\Domain\CandidateLead\Event\CandidateLeadIngested;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Uid\Uuid;

/**
 * Projette les domain events notables dans le centre de notifications (V2). Même patron que le
 * journal `interaction` : consommé en ASYNCHRONE par le worker (tenant activé par message → RLS),
 * idempotent via l'unicité d'`event_id` (ON CONFLICT DO NOTHING — les retries Messenger ne créent
 * jamais de doublon). Les events consommés sont du « langage publié » (Event\ d'autres contextes,
 * autorisé par la frontière cross-contexte).
 */
final class NotificationProjector
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** Le moment le plus précieux du produit : une réponse est arrivée. */
    #[AsMessageHandler(bus: 'event.bus')]
    public function onReplyCaptured(ReplyCaptured $event): void
    {
        $this->record($event, $event->tenantId, 'reply_received', [
            'leadId' => $event->leadId,
            'preview' => mb_substr($event->preview, 0, 140),
        ]);
    }

    /** Un envoi a échoué : l'utilisatrice doit le savoir (le message ne partira pas tout seul). */
    #[AsMessageHandler(bus: 'event.bus')]
    public function onEmailSendFailed(EmailSendFailed $event): void
    {
        $this->record($event, $event->tenantId, 'email_send_failed', [
            'leadId' => $event->leadId,
            'reason' => $event->reason,
        ]);
    }

    /** La boîte email nécessite une RECONNEXION (token OAuth mort) : l'utilisatrice doit agir. */
    #[AsMessageHandler(bus: 'event.bus')]
    public function onMailboxSyncFailed(MailboxSyncFailed $event): void
    {
        // On ne notifie QUE le cas actionnable : un incident transitoire (sync_failed) se résout
        // au prochain tick, inutile d'alarmer. `reauth_required` = seule une reconnexion relance la boîte.
        if ('reauth_required' !== $event->reason) {
            return;
        }

        $this->record($event, $event->tenantId, 'mailbox_disconnected', ['reason' => $event->reason]);
    }

    /** Une annonce est entrée dans la file « À trier » : un candidat à évaluer. */
    #[AsMessageHandler(bus: 'event.bus')]
    public function onCandidateLeadIngested(CandidateLeadIngested $event): void
    {
        $this->record($event, $event->tenantId, 'candidate_to_triage', [
            'candidateLeadId' => $event->candidateLeadId,
            'source' => $event->source,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function record(DomainEvent $event, string $tenantId, string $type, array $payload): void
    {
        $this->connection->executeStatement(
            'INSERT INTO notification (id, event_id, tenant_id, type, payload, occurred_on)
             VALUES (:id, :event_id, :tenant_id, :type, :payload, :occurred_on)
             ON CONFLICT (event_id) DO NOTHING',
            [
                'id' => Uuid::v7()->toRfc4122(),
                'event_id' => $event->eventId(),
                'tenant_id' => $tenantId,
                'type' => $type,
                'payload' => json_encode($payload, \JSON_THROW_ON_ERROR),
                'occurred_on' => $event->occurredOn()->format('Y-m-d H:i:s.u'),
            ],
        );
    }
}
