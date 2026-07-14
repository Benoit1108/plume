<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Http;

use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Plafonne les envois (POST /drafts/{id}/send) PAR TENANT : chaque envoi
 * engage la réputation de la boîte. Priorité 0 : après le firewall.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final class MailSendRateLimitListener
{
    private const string PATH_PATTERN = '#^/api/v1/drafts/[^/]+/send$#';

    public function __construct(
        private readonly RateLimiterFactory $mailboxSendLimiter,
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

        $key = $this->tenantContext->get()?->toString() ?? ('ip-'.($request->getClientIp() ?? 'unknown'));

        $limit = $this->mailboxSendLimiter->create($key)->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time());
        }
    }
}
