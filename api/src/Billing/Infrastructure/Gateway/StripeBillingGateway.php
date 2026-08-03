<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Gateway;

use App\Billing\Application\BillingGateway;
use App\Billing\Application\Exception\BillingGatewayFailed;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ACL Stripe (HTTP fin, pas de SDK — même patron que Gmail/Graph). Crée les sessions Checkout
 * (abonnement) et Portail client. Le `tenant_id` voyage en `client_reference_id` + metadata pour que
 * le webhook (StripeWebhookController) relie le paiement au bon compte. L'activation réelle se fait
 * par WEBHOOK, jamais ici (ne jamais faire confiance au retour de redirection pour créditer l'accès).
 */
final class StripeBillingGateway implements BillingGateway
{
    private const string CHECKOUT_ENDPOINT = 'https://api.stripe.com/v1/checkout/sessions';
    private const string PORTAL_ENDPOINT = 'https://api.stripe.com/v1/billing_portal/sessions';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $secretKey,
        private readonly string $priceMonthly,
        private readonly string $priceAnnual,
        private readonly string $frontBaseUrl,
    ) {
    }

    public function createCheckoutSession(string $tenantId, string $email, string $plan): string
    {
        $price = 'annual' === $plan ? $this->priceAnnual : $this->priceMonthly;

        return $this->post(self::CHECKOUT_ENDPOINT, [
            'mode' => 'subscription',
            'line_items[0][price]' => $price,
            'line_items[0][quantity]' => '1',
            'customer_email' => $email,
            'client_reference_id' => $tenantId,
            'subscription_data[metadata][tenant_id]' => $tenantId,
            'success_url' => $this->frontBaseUrl.'/settings?billing=success',
            'cancel_url' => $this->frontBaseUrl.'/settings?billing=cancel',
        ]);
    }

    public function createPortalSession(string $customerId): string
    {
        return $this->post(self::PORTAL_ENDPOINT, [
            'customer' => $customerId,
            'return_url' => $this->frontBaseUrl.'/settings',
        ]);
    }

    /**
     * @param array<string, string> $body
     */
    private function post(string $endpoint, array $body): string
    {
        try {
            $payload = $this->httpClient->request('POST', $endpoint, [
                'auth_bearer' => $this->secretKey,
                'body' => $body, // form-urlencoded (attendu par l'API Stripe)
                'timeout' => 20,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw BillingGatewayFailed::because('Stripe session creation failed.', $e);
        }

        $url = $payload['url'] ?? null;
        if (!\is_string($url) || '' === $url) {
            throw BillingGatewayFailed::because('Stripe returned no session URL.');
        }

        return $url;
    }
}
