<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Scheduler;

use App\Mailbox\Application\Command\FetchReplies\FetchReplies;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Éventail par tenant : chaque boîte CONNECTED relève ses réponses dans un message dédié sur la
 * file `io` — consommée par le worker plume_app dédié (tenant activé → RLS appliquée), isolation
 * de panne ET de charge (l'I/O IMAP/Graph, hors du process scheduler, n'affame pas les projections
 * légères de `async`, ADR-0022 §5). Aligné sur PollAllSources/FetchAllAlertEmails (le fan-out ne
 * fait QUE énumération + dispatch, aucune logique métier tenantée ici).
 */
#[AsMessageHandler]
final class FetchAllRepliesHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(FetchAllRepliesTick $tick): void
    {
        /** @var list<string> $tenants */
        $tenants = $this->connection->fetchFirstColumn(
            // RGPD : on ne relève plus rien pour un compte en cours de suppression (soft-delete).
            "SELECT tenant_id FROM connected_mailbox WHERE status = 'CONNECTED'
             AND tenant_id NOT IN (SELECT tenant_id FROM app_user WHERE deletion_requested_at IS NOT NULL)",
        );
        foreach ($tenants as $tenantId) {
            $this->commandBus->dispatch(new FetchReplies($tenantId), [new TransportNamesStamp(['io'])]);
        }
    }
}
