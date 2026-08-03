<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Scheduler;

use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * Vitrine — purge des comptes de démo expirés. Énumère les tenants démo (`demo_expires_at` dépassé)
 * et RÉUTILISE la purge RGPD : un `PurgeAccount` par tenant sur `async` → effacement atomique et
 * isolé de toutes les tables tenantées + app_user (même garanties que la suppression de compte).
 * Fan-out cross-tenant légitime (scheduler propriétaire ; `app_user` hors RLS).
 */
#[AsMessageHandler]
final class PurgeExpiredDemosHandler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(PurgeExpiredDemosTick $tick): void
    {
        /** @var list<array{tenant_id: string, email: string}> $expired */
        $expired = $this->connection->fetchAllAssociative(
            'SELECT tenant_id, email FROM app_user WHERE demo_expires_at IS NOT NULL AND demo_expires_at < :now',
            ['now' => $this->clock->now()->format('Y-m-d H:i:s')],
        );

        foreach ($expired as $account) {
            $this->commandBus->dispatch(
                new PurgeAccount($account['tenant_id'], $account['email']),
                [new TransportNamesStamp(['async'])],
            );
        }
    }
}
