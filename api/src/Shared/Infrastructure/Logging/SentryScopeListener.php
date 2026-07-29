<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Logging;

use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Corrèle les événements Sentry SANS PII : ajoute `tenant_id` + `request_id` comme tags de scope.
 * On sait ainsi QUEL compte et QUELLE requête ont produit une erreur, sans jamais y mettre d'email
 * ni de contenu (cohérent avec `send_default_pii: false`).
 *
 * INERTE par défaut : si aucun client Sentry n'est configuré (DSN vide, ou hors prod où le bundle
 * n'est même pas chargé), on ne fait rien — le listener est donc sans effet et sans risque partout.
 * Priorité basse (< firewall) pour que le tenant, posé à l'authentification, soit déjà disponible.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]
final class SentryScopeListener
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly CorrelationContext $correlation,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || null === SentrySdk::getCurrentHub()->getClient()) {
            return; // Sentry inactif : rien à corréler.
        }

        $tenant = $this->tenantContext->get();
        $requestId = $this->correlation->get();

        \Sentry\configureScope(static function (Scope $scope) use ($tenant, $requestId): void {
            if (null !== $tenant) {
                $scope->setTag('tenant_id', $tenant->toString());
            }
            if (null !== $requestId) {
                $scope->setTag('request_id', $requestId);
            }
        });
    }
}
