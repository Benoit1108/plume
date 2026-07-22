<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/**
 * Isolation multi-tenant côté WORKER — symétrique au listener JWT (HTTP). Pour tout message
 * consommé par le VRAI worker, on garantit une ardoise propre : le tenant est remis à zéro
 * avant ET après le traitement, pour qu'aucun tenant ne fuite vers le message suivant sur le
 * même process (FrankenPHP le réutilise). Le tenant utile est posé par le handler/la politique
 * (`TenantScope::activate`) à partir de la commande/de l'event.
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
        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->tenantScope->clear();
        }
    }
}
