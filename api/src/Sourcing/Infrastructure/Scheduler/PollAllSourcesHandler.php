<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Scheduler;

use App\Shared\Application\Command\CommandBus;
use App\Sourcing\Application\Command\PollAlertSource\PollAlertSource;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Éventail par tenant : chaque tenant ayant ≥ 1 flux actif est relevé (tenant explicite). */
#[AsMessageHandler]
final class PollAllSourcesHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly CommandBus $commandBus,
    ) {
    }

    public function __invoke(PollAllSourcesTick $tick): void
    {
        /** @var list<string> $tenants */
        $tenants = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT tenant_id FROM alert_feed WHERE active = true',
        );
        foreach ($tenants as $tenantId) {
            $this->commandBus->dispatch(new PollAlertSource($tenantId));
        }
    }
}
