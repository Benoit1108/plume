<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Scheduler;

use App\Sourcing\Application\Command\PollAlertSource\PollAlertSource;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Éventail par tenant : chaque tenant ayant ≥ 1 flux actif est relevé dans un message
 * ASYNCHRONE dédié (isolation de panne — l'échec/retry d'un tenant n'affecte pas les autres,
 * pas de transaction commune, l'I/O réseau ne bloque pas la tâche de fan-out).
 */
#[AsMessageHandler]
final class PollAllSourcesHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(PollAllSourcesTick $tick): void
    {
        /** @var list<string> $tenants */
        $tenants = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT tenant_id FROM alert_feed WHERE active = true',
        );
        foreach ($tenants as $tenantId) {
            $this->commandBus->dispatch(new PollAlertSource($tenantId), [new TransportNamesStamp(['async'])]);
        }
    }
}
