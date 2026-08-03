<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Gateway;

use App\Billing\Application\BillingGateway;
use App\Billing\Application\Subscriptions;
use App\Shared\Application\Clock;

/**
 * Passerelle de paiement FACTICE (défaut sans clés Stripe : dev/CI/E2E). Simule un paiement RÉUSSI
 * INSTANTANÉ : le checkout active directement l'abonnement (comme le ferait le webhook Stripe) et
 * renvoie l'URL de retour de l'app. Permet de tester le parcours « s'abonner → actif » sans réseau
 * ni compte Stripe.
 */
final class FakeBillingGateway implements BillingGateway
{
    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly Clock $clock,
        private readonly string $frontBaseUrl,
    ) {
    }

    public function createCheckoutSession(string $tenantId, string $email, string $plan): string
    {
        // Paiement simulé : on active tout de suite (le vrai flux passe par le webhook Stripe).
        $this->subscriptions->activate(
            $tenantId,
            'cus_fake_'.$tenantId,
            'sub_fake_'.$tenantId,
            $this->clock->now()->modify('annual' === $plan ? '+1 year' : '+1 month'),
        );

        return $this->frontBaseUrl.'/settings?billing=success';
    }

    public function createPortalSession(string $customerId): string
    {
        return $this->frontBaseUrl.'/settings';
    }
}
