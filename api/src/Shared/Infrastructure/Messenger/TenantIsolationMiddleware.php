<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/**
 * Isolation multi-tenant côté WORKER — symétrique au listener JWT (HTTP). Pour tout message
 * consommé par le VRAI worker et porteur d'un tenant, on ACTIVE ce tenant (filtre Doctrine +,
 * à terme, variable de session RLS) avant traitement, puis on remet à zéro APRÈS — de sorte
 * que TOUT handler worker (projecteurs, politiques, commandes async) soit scopé uniformément,
 * et qu'aucun tenant ne fuite vers le message suivant sur un process réutilisé (FrankenPHP).
 *
 * Le tenant est lu par convention (propriété publique `tenantId`, portée par les commandes et
 * domain events tenantés) ou via l'interface `TenantAware`. Les messages non tenantés (ticks de
 * maintenance cross-tenant) ne sont pas activés.
 *
 * On teste `ConsumedByWorkerStamp` (posé UNIQUEMENT par `messenger:consume`), et non
 * `ReceivedStamp` : le transport `sync://` (env test, dispatch HTTP en ligne) pose aussi
 * `ReceivedStamp` — l'utiliser effacerait le tenant de la requête HTTP en cours.
 */
final class TenantIsolationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly TenantScope $tenantScope)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(ConsumedByWorkerStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $this->tenantScope->clear();
        $tenantId = $this->tenantOf($envelope->getMessage());
        if (null !== $tenantId) {
            $this->tenantScope->activate(TenantId::fromString($tenantId));
        }
        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->tenantScope->clear();
        }
    }

    private function tenantOf(object $message): ?string
    {
        if ($message instanceof TenantAware) {
            return $message->messageTenantId();
        }
        if (property_exists($message, 'tenantId')) {
            $value = (new \ReflectionProperty($message, 'tenantId'))->getValue($message);

            return \is_string($value) && '' !== $value ? $value : null;
        }

        return null;
    }
}
