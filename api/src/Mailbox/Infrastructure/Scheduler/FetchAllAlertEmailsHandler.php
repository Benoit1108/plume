<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Scheduler;

use App\Mailbox\Application\Command\FetchAlertEmails\FetchAlertEmails;
use App\Shared\Application\Command\CommandBus;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Éventail par tenant : chaque boîte CONNECTED relève ses alertes (tenant explicite). */
#[AsMessageHandler]
final class FetchAllAlertEmailsHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CommandBus $commandBus,
    ) {
    }

    public function __invoke(FetchAllAlertEmailsTick $tick): void
    {
        /** @var list<string> $tenants */
        $tenants = $this->connection->fetchFirstColumn(
            "SELECT tenant_id FROM connected_mailbox WHERE status = 'CONNECTED'",
        );
        foreach ($tenants as $tenantId) {
            $this->commandBus->dispatch(new FetchAlertEmails($tenantId));
        }
    }
}
