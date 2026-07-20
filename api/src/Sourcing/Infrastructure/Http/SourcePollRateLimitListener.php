<?php

declare(strict_types=1);

namespace App\Sourcing\Infrastructure\Http;

use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Plafonne la relève manuelle (POST /sources/poll) PAR TENANT : elle fait un I/O réseau
 * synchrone et déclenche des requêtes sortantes. Priorité 0 : après le firewall.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final class SourcePollRateLimitListener
{
    private const string PATH = '/api/v1/sources/poll';

    public function __construct(
        private readonly RateLimiterFactory $sourcesPollLimiter,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('POST' !== $request->getMethod() || self::PATH !== $request->getPathInfo()) {
            return;
        }

        $key = $this->tenantContext->get()?->toString() ?? ('ip-'.($request->getClientIp() ?? 'unknown'));

        $limit = $this->sourcesPollLimiter->create($key)->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }
    }
}
