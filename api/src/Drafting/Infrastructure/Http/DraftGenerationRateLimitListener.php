<?php

declare(strict_types=1);

namespace App\Drafting\Infrastructure\Http;

use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Plafonne les demandes de génération (POST /leads/{id}/drafts et
 * /drafts/{id}/regenerate) PAR TENANT : chaque appel peut déclencher une
 * dépense API Anthropic et occuper le worker. Priorité 0 : après le firewall,
 * le tenant du JWT est disponible.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final class DraftGenerationRateLimitListener
{
    private const string PATH_PATTERN = '#^/api/v1/(?:leads/[^/]+/drafts|drafts/[^/]+/regenerate)$#';

    public function __construct(
        private readonly RateLimiterFactory $draftingGenerationLimiter,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ('POST' !== $request->getMethod() || 1 !== preg_match(self::PATH_PATTERN, $request->getPathInfo())) {
            return;
        }

        // Fail-closed : sans tenant (requête anonyme, le firewall répondra 401),
        // on limite par IP plutôt que de laisser passer sans compter.
        $key = $this->tenantContext->get()?->toString() ?? ('ip-'.($request->getClientIp() ?? 'unknown'));

        $limit = $this->draftingGenerationLimiter->create($key)->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }
    }
}
