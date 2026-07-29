<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use App\Shared\Infrastructure\Logging\CorrelationContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Uid\Uuid;

/**
 * Attribue un identifiant de corrélation à chaque requête HTTP, très tôt (avant tout autre
 * traitement), pour que TOUS les logs de la requête — et l'async qu'elle déclenche — le portent.
 *
 * Un en-tête `X-Request-Id` entrant (proxy/monitoring en amont) est RÉUTILISÉ s'il a une forme sûre
 * (jamais fait confiance à l'aveugle : bornage + liste blanche de caractères → pas d'injection dans
 * les logs) ; sinon on génère un UUID v7. L'id est renvoyé dans la réponse (`X-Request-Id`) pour que
 * le client/support puisse le citer. Remis à zéro en fin de requête (anti-fuite mode worker FrankenPHP).
 */
#[AsEventListener(event: KernelEvents::REQUEST, method: 'onRequest', priority: 8000)]
#[AsEventListener(event: KernelEvents::RESPONSE, method: 'onResponse')]
#[AsEventListener(event: KernelEvents::TERMINATE, method: 'onTerminate')]
final class CorrelationIdListener
{
    private const HEADER = 'X-Request-Id';
    private const SAFE = '/^[A-Za-z0-9._-]{1,128}$/';

    public function __construct(private readonly CorrelationContext $correlation)
    {
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $incoming = (string) $event->getRequest()->headers->get(self::HEADER, '');
        $requestId = 1 === preg_match(self::SAFE, $incoming) ? $incoming : Uuid::v7()->toRfc4122();
        $this->correlation->set($requestId);
    }

    public function onResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $requestId = $this->correlation->get();
        if (null !== $requestId) {
            $event->getResponse()->headers->set(self::HEADER, $requestId);
        }
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $this->correlation->clear();
    }
}
