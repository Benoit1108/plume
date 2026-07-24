<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Scheduler;

use App\Sourcing\Application\Command\PollAlertSource\PollAlertSource;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Éventail par tenant : chaque tenant ayant ≥ 1 flux actif est relevé dans un message dédié sur
 * la file `io` (worker plume_app séparé) — isolation de panne (l'échec/retry d'un tenant n'affecte
 * pas les autres, pas de transaction commune) ET de charge : l'I/O réseau RSS n'affame pas les
 * projections légères de `async` (ADR-0022 §5).
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
            // RGPD : on ne relève plus rien pour un compte en cours de suppression (soft-delete).
            'SELECT DISTINCT tenant_id FROM alert_feed WHERE active = true
             AND tenant_id NOT IN (SELECT tenant_id FROM app_user WHERE deletion_requested_at IS NOT NULL)',
        );
        foreach ($tenants as $tenantId) {
            $this->commandBus->dispatch(new PollAlertSource($tenantId), [new TransportNamesStamp(['io'])]);
        }
    }
}
