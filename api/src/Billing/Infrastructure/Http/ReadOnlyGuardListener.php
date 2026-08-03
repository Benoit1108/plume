<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http;

use App\Billing\Application\Subscriptions;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Garde « LECTURE SEULE » (V2.2) : quand un compte n'a plus le droit d'écrire (essai expiré, impayé,
 * résiliation), les écritures PRODUIT sont refusées (402 `subscription_required`) — mais tout reste
 * consultable, exportable et gérable (compte / abonnement / suppression).
 *
 * Placé sur CONTROLLER (après l'auth JWT qui pose le tenant, avant le contrôleur). N'intervient que
 * sur les requêtes MUTANTES d'un tenant authentifié, hors liste blanche (auth, inscription, compte,
 * back-office, billing). Un compte sans abonnement (antérieur à la facturation) reste en droit.
 */
#[AsEventListener(event: ControllerEvent::class)]
final class ReadOnlyGuardListener
{
    private const array WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /** Toujours autorisé, même en lecture seule : s'authentifier, s'inscrire, gérer son compte, régler, payer. */
    private const array ALLOWED_PREFIXES = [
        '/api/v1/token',       // refresh / invalidate / sessions
        '/api/v1/login_check',
        '/api/v1/register',
        '/api/v1/account',     // mot de passe, 2FA, vérif email, export RGPD, suppression
        '/api/v1/profile',     // réglages (objectif, présentation, préférences)
        '/api/v1/admin',
        '/api/v1/billing',     // s'abonner / gérer l'abonnement / webhooks (slice 2)
    ];

    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public function __invoke(ControllerEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if (!\in_array($request->getMethod(), self::WRITE_METHODS, true)) {
            return;
        }

        $path = $request->getPathInfo();
        foreach (self::ALLOWED_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        // Pas de tenant = requête non authentifiée : on laisse le firewall décider (401).
        $tenant = $this->tenantContext->get();
        if (null === $tenant) {
            return;
        }

        if (!$this->subscriptions->isEntitled($tenant->toString())) {
            throw new HttpException(Response::HTTP_PAYMENT_REQUIRED, 'subscription_required');
        }
    }
}
