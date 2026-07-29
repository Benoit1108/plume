<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messenger;

use App\Shared\Infrastructure\Logging\CorrelationContext;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ConsumedByWorkerStamp;

/**
 * Propage l'identifiant de corrélation à travers le bus, symétrique de {@see TenantIsolationMiddleware} :
 *
 * - À l'ENVOI (dispatch HTTP) : estampille le message avec le `request_id` courant s'il existe et
 *   n'est pas déjà posé → il est sérialisé avec le message vers le transport async.
 * - À la CONSOMMATION worker (ConsumedByWorkerStamp posé par `messenger:consume`) : active le
 *   `request_id` de l'estampille le temps du handler, puis remet à zéro (anti-fuite process réutilisé).
 *
 * On ne teste que `ConsumedByWorkerStamp` (jamais `ReceivedStamp`, que `sync://` pose aussi en test/HTTP
 * — l'utiliser effacerait la corrélation de la requête en cours).
 */
final class CorrelationMiddleware implements MiddlewareInterface
{
    public function __construct(private readonly CorrelationContext $correlation)
    {
    }

    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null !== $envelope->last(ConsumedByWorkerStamp::class)) {
            $stamp = $envelope->last(CorrelationStamp::class);
            if (null !== $stamp) {
                $this->correlation->set($stamp->requestId);
            }
            try {
                return $stack->next()->handle($envelope, $stack);
            } finally {
                $this->correlation->clear();
            }
        }

        // Côté envoi : attacher le request_id courant (une seule fois).
        if (null === $envelope->last(CorrelationStamp::class)) {
            $requestId = $this->correlation->get();
            if (null !== $requestId) {
                $envelope = $envelope->with(new CorrelationStamp($requestId));
            }
        }

        return $stack->next()->handle($envelope, $stack);
    }
}
