<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Gateway;

use App\Billing\Application\BillingGateway;

/**
 * Route vers Stripe si une clé secrète est configurée, sinon vers l'adaptateur FACTICE (dev/CI/E2E).
 * Même politique que les registres de messagerie (Gmail/Outlook).
 */
final class ProviderBillingGateway implements BillingGateway
{
    public function __construct(
        private readonly FakeBillingGateway $fake,
        private readonly StripeBillingGateway $stripe,
        private readonly string $secretKey,
    ) {
    }

    public function createCheckoutSession(string $tenantId, string $email, string $plan): string
    {
        return $this->gateway()->createCheckoutSession($tenantId, $email, $plan);
    }

    public function createPortalSession(string $customerId): string
    {
        return $this->gateway()->createPortalSession($customerId);
    }

    private function gateway(): BillingGateway
    {
        return '' === trim($this->secretKey) ? $this->fake : $this->stripe;
    }
}
