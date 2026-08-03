<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http;

use App\Account\Infrastructure\Persistence\User;
use App\Billing\Application\Subscriptions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * V2.2 — état d'abonnement pour l'UI (GET /api/v1/billing/subscription, authentifié). Renseigne la
 * page Compte (statut, fin d'essai, en droit ou non, portail ouvrable) et pilote le bandeau
 * « lecture seule ». Comptages/état seulement, pas de PII.
 */
#[AsController]
final class SubscriptionStatusController
{
    public function __construct(
        private readonly Security $security,
        private readonly Subscriptions $subscriptions,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        return new JsonResponse($this->subscriptions->snapshot($user->getTenantId()->toRfc4122()));
    }
}
