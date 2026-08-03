<?php

declare(strict_types=1);

namespace App\Tests\Billing\Infrastructure\Http;

use App\Account\Infrastructure\Persistence\User;
use App\Billing\Application\BillingGateway;
use App\Billing\Application\Exception\BillingGatewayFailed;
use App\Billing\Application\Subscriptions;
use App\Billing\Infrastructure\Http\CheckoutController;
use App\Billing\Infrastructure\Http\PortalController;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;

/**
 * Billing — quand le fournisseur de paiement échoue, checkout ET portail répondent 502
 * `billing_unavailable` (jamais une 500 nue). Le port BillingGateway est remplacé par un double
 * qui lève systématiquement.
 */
final class BillingGatewayFailureTest extends TestCase
{
    public function testCheckoutReturns502OnGatewayFailure(): void
    {
        $controller = new CheckoutController($this->securityWithUser(), $this->failingGateway());

        $response = $controller(Request::create('/api/v1/billing/checkout', 'POST', content: '{"plan":"monthly"}'));

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertStringContainsString('billing_unavailable', (string) $response->getContent());
    }

    public function testPortalReturns502OnGatewayFailure(): void
    {
        $subscriptions = $this->createStub(Subscriptions::class);
        $subscriptions->method('stripeCustomerFor')->willReturn('cus_x'); // a déjà payé → on tente le portail
        $controller = new PortalController($this->securityWithUser(), $subscriptions, $this->failingGateway());

        $response = $controller();

        self::assertSame(Response::HTTP_BAD_GATEWAY, $response->getStatusCode());
        self::assertStringContainsString('billing_unavailable', (string) $response->getContent());
    }

    private function securityWithUser(): Security
    {
        $user = new User(Uuid::v7(), Uuid::v7(), 'billing@plume.test');
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($user);

        return $security;
    }

    private function failingGateway(): BillingGateway
    {
        return new class implements BillingGateway {
            public function createCheckoutSession(string $tenantId, string $email, string $plan): string
            {
                throw BillingGatewayFailed::because('boom');
            }

            public function createPortalSession(string $customerId): string
            {
                throw BillingGatewayFailed::because('boom');
            }
        };
    }
}
