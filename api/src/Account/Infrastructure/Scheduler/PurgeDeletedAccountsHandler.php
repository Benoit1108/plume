<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Scheduler;

use App\Shared\Application\Clock;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * RGPD — fan-out de la purge (V2.0-a2, revue V2.0 P1). Ce tick énumère les comptes dont la
 * suppression a été demandée il y a plus du délai de grâce (30 j) et émet UN message `PurgeAccount`
 * par compte sur `async` — chacun est ensuite traité dans SA PROPRE transaction (command.bus),
 * isolant les pannes : l'échec de la purge d'un compte ne bloque plus les autres (le défaut de la
 * boucle imbriquée sous `doctrine_transaction`, corrigé ici).
 *
 * Le fan-out lui-même ne fait QUE énumérer + dispatcher (aucune logique tenantée) ; il tourne sur le
 * scheduler propriétaire. `app_user` n'est pas sous RLS → l'énumération cross-tenant y est légitime.
 */
#[AsMessageHandler]
final class PurgeDeletedAccountsHandler
{
    private const string GRACE_PERIOD = '-30 days';

    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock,
        private readonly MessageBusInterface $commandBus,
    ) {
    }

    public function __invoke(PurgeDeletedAccountsTick $tick): void
    {
        $cutoff = $this->clock->now()->modify(self::GRACE_PERIOD)->format('Y-m-d H:i:s');

        /** @var list<array{tenant_id: string, email: string}> $expired */
        $expired = $this->connection->fetchAllAssociative(
            'SELECT tenant_id, email FROM app_user
             WHERE deletion_requested_at IS NOT NULL AND deletion_requested_at < :cutoff',
            ['cutoff' => $cutoff],
        );

        foreach ($expired as $account) {
            $this->commandBus->dispatch(
                new PurgeAccount($account['tenant_id'], $account['email']),
                [new TransportNamesStamp(['async'])],
            );
        }
    }
}
