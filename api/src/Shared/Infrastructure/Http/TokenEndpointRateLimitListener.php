<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Anti force brute sur les endpoints token publics (/token/refresh, /token/invalidate),
 * par IP. Priorité > 8 pour s'exécuter avant le firewall.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 16)]
final class TokenEndpointRateLimitListener
{
    private const PATHS = ['/api/v1/token/refresh', '/api/v1/token/invalidate'];

    public function __construct(private readonly RateLimiterFactory $tokenEndpointsLimiter)
    {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!\in_array($request->getPathInfo(), self::PATHS, true) || 'POST' !== $request->getMethod()) {
            return;
        }

        $limit = $this->tokenEndpointsLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }
    }
}
