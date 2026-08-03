<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http;

use App\Account\Infrastructure\Persistence\User;
use App\Billing\Application\BillingGateway;
use App\Billing\Application\Exception\BillingGatewayFailed;
use App\Billing\Application\Subscriptions;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * V2.2 — portail client (POST /api/v1/billing/portal, authentifié) : gérer / annuler son abonnement,
 * mettre à jour la carte. 409 si le compte n'a jamais payé (aucun client Stripe à gérer).
 */
#[AsController]
final class PortalController
{
    public function __construct(
        private readonly Security $security,
        private readonly Subscriptions $subscriptions,
        private readonly BillingGateway $gateway,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();
        \assert($user instanceof User);

        $customer = $this->subscriptions->stripeCustomerFor($user->getTenantId()->toRfc4122());
        if (null === $customer) {
            return new JsonResponse(['detail' => 'no_subscription'], Response::HTTP_CONFLICT);
        }

        try {
            $url = $this->gateway->createPortalSession($customer);
        } catch (BillingGatewayFailed) {
            return new JsonResponse(['detail' => 'billing_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['url' => $url]);
    }
}
