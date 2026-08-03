<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Http;

use App\Account\Infrastructure\Persistence\User;
use App\Billing\Application\BillingGateway;
use App\Billing\Application\Exception\BillingGatewayFailed;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * V2.2 — démarre le paiement (POST /api/v1/billing/checkout, authentifié). Renvoie l'URL de la
 * session Checkout (Stripe réel, ou retour immédiat en factice). L'ACCÈS n'est crédité que par le
 * webhook (jamais sur la foi de cette redirection). `plan` = monthly (défaut) | annual.
 */
#[AsController]
final class CheckoutController
{
    public function __construct(
        private readonly Security $security,
        private readonly BillingGateway $gateway,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();
        \assert($user instanceof User); // derrière le firewall : toujours présent

        $payload = json_decode($request->getContent(), true);
        $plan = \is_array($payload) && 'annual' === ($payload['plan'] ?? null) ? 'annual' : 'monthly';

        try {
            $url = $this->gateway->createCheckoutSession($user->getTenantId()->toRfc4122(), $user->getUserIdentifier(), $plan);
        } catch (BillingGatewayFailed) {
            return new JsonResponse(['detail' => 'billing_unavailable'], Response::HTTP_BAD_GATEWAY);
        }

        return new JsonResponse(['url' => $url]);
    }
}
