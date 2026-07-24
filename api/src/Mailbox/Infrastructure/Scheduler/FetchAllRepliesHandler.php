<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Scheduler;

use App\Mailbox\Application\Command\FetchReplies\FetchReplies;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Éventail par tenant : chaque boîte CONNECTED relève ses réponses dans un message ASYNCHRONE
 * dédié — consommé par le worker (rôle plume_app, tenant activé → RLS appliquée), isolation de
 * panne, I/O IMAP/Graph hors du process scheduler. Aligné sur PollAllSources/FetchAllAlertEmails
 * (le fan-out ne fait QUE énumération + dispatch, aucune logique métier tenantée ici).
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
            "SELECT tenant_id FROM connected_mailbox WHERE status = 'CONNECTED'",
        );
        foreach ($tenants as $tenantId) {
            $this->commandBus->dispatch(new FetchReplies($tenantId), [new TransportNamesStamp(['async'])]);
        }
    }
}
