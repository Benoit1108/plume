<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Scheduler;

use App\Mailbox\Application\Command\FetchAlertEmails\FetchAlertEmails;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Éventail par tenant : chaque boîte CONNECTED relève ses alertes dans un message ASYNCHRONE
 * dédié (isolation de panne + I/O IMAP/Graph hors de la tâche de fan-out).
 */
#[AsMessageHandler]
final class FetchAllAlertEmailsHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(FetchAllAlertEmailsTick $tick): void
    {
        /** @var list<string> $tenants */
        $tenants = $this->connection->fetchFirstColumn(
            "SELECT tenant_id FROM connected_mailbox WHERE status = 'CONNECTED'",
        );
        foreach ($tenants as $tenantId) {
            $this->commandBus->dispatch(new FetchAlertEmails($tenantId), [new TransportNamesStamp(['async'])]);
        }
    }
}
