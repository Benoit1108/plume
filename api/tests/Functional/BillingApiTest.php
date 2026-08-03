<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Account\Infrastructure\Persistence\User;
use App\Billing\Application\Subscriptions;
use App\Billing\Infrastructure\Http\StripeWebhookController;
use App\Billing\Infrastructure\Persistence\DoctrineSubscriptions;
use App\Shared\Application\Clock;
use App\Tests\Support\FixedClock;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/**
 * V2.2 — abonnement : démarrage d'essai (idempotent), calcul du droit d'accès (essai valide/expiré,
 * grandfathered), et GARDE lecture seule côté HTTP (écriture produit refusée 402, gestion du compte
 * et réglages toujours permis).
 */
final class BillingApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens, subscription, stripe_webhook_event, organization, lead, profile RESTART IDENTITY CASCADE');

        $limiter = static::getContainer()->get('limiter.token_endpoints');
        \assert($limiter instanceof RateLimiterFactory);
        $limiter->create('127.0.0.1')->reset();
    }

    private function createUser(string $email): string
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $tenant = Uuid::v7();
        $user = new User(Uuid::v7(), $tenant, $email);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);
        $em->flush();
        $em->clear();

        return $tenant->toRfc4122();
    }

    private function tokenFor(Client $client, string $email): string
    {
        /** @var array{token: string} $data */
        $data = $client->request('POST', '/api/v1/login_check', ['json' => ['email' => $email, 'password' => self::PASSWORD]])->toArray();

        return $data['token'];
    }

    private function seedSubscription(string $tenantId, string $status, ?string $trialEndsAt): void
    {
        $this->connection->executeStatement(
            'INSERT INTO subscription (tenant_id, status, trial_ends_at, updated_at) VALUES (?, ?, ?, NOW())',
            [$tenantId, $status, $trialEndsAt],
        );
    }

    public function testStartTrialIsIdempotentAndDrivesEntitlement(): void
    {
        $tenant = $this->createUser('trial@plume.test');
        $subs = new DoctrineSubscriptions($this->connection, new FixedClock(new \DateTimeImmutable('2026-08-03 10:00:00')));

        $subs->startTrial($tenant);
        $subs->startTrial($tenant); // rejoué → pas de doublon, essai inchangé

        $rows = $this->connection->fetchOne('SELECT COUNT(*) FROM subscription WHERE tenant_id = ?', [$tenant]);
        self::assertSame(1, is_numeric($rows) ? (int) $rows : -1);
        self::assertTrue($subs->isEntitled($tenant)); // essai en cours (J+0 sur 14)

        // Même abonnement, horloge après l'expiration de l'essai (14 j) → plus de droit.
        $afterTrial = new DoctrineSubscriptions($this->connection, new FixedClock(new \DateTimeImmutable('2026-08-20 10:00:00')));
        self::assertFalse($afterTrial->isEntitled($tenant));

        // Compte sans abonnement (antérieur à la facturation) → droit conservé (grandfathered).
        self::assertTrue($subs->isEntitled($this->createUser('legacy@plume.test')));
    }

    public function testReadOnlyGuardBlocksProductWritesButAllowsAccountManagement(): void
    {
        $tenant = $this->createUser('expired@plume.test');
        $this->seedSubscription($tenant, 'trialing', '2000-01-01 00:00:00'); // essai expiré depuis longtemps

        $client = static::createClient();
        $token = $this->tokenFor($client, 'expired@plume.test');

        // Écriture PRODUIT → refusée (402, lecture seule).
        $client->request('POST', '/api/v1/organizations', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Éditions Bloquées', 'type' => 'PUBLISHER'],
        ]);
        self::assertResponseStatusCodeSame(402);

        // Consultation (GET) → toujours permise.
        $client->request('GET', '/api/v1/organizations', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();

        // Réglages du compte (PATCH /profile) → permis même en lecture seule.
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['weeklyGoal' => 7],
        ]);
        self::assertResponseIsSuccessful();
    }

    public function testCheckoutViaFakeGatewayLiftsReadOnly(): void
    {
        // Essai expiré → lecture seule. Le checkout (factice) active l'abonnement → écriture rétablie.
        $tenant = $this->createUser('subscribe@plume.test');
        $this->seedSubscription($tenant, 'trialing', '2000-01-01 00:00:00');

        $client = static::createClient();
        $token = $this->tokenFor($client, 'subscribe@plume.test');

        /** @var array{url: string} $checkout */
        $checkout = $client->request('POST', '/api/v1/billing/checkout', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['plan' => 'monthly'],
        ])->toArray();
        self::assertStringContainsString('/settings', $checkout['url']); // retour app (factice)

        // Désormais abonné → l'écriture produit repasse.
        $client->request('POST', '/api/v1/organizations', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Éditions Abonnées', 'type' => 'PUBLISHER'],
        ]);
        self::assertResponseStatusCodeSame(201);
    }

    public function testSubscriptionSnapshotReflectsState(): void
    {
        // Compte grandfathered (aucun abonnement) → status none, en droit.
        $this->createUser('snap-legacy@plume.test');
        // Compte en essai valide.
        $trial = $this->createUser('snap-trial@plume.test');
        $this->seedSubscription($trial, 'trialing', '2099-01-01 00:00:00');

        $client = static::createClient();

        /** @var array{status: string, entitled: bool, canManage: bool} $legacy */
        $legacy = $client->request('GET', '/api/v1/billing/subscription', ['auth_bearer' => $this->tokenFor($client, 'snap-legacy@plume.test')])->toArray();
        self::assertSame('none', $legacy['status']);
        self::assertTrue($legacy['entitled']);
        self::assertFalse($legacy['canManage']);

        /** @var array{status: string, entitled: bool, trialEndsAt: ?string} $onTrial */
        $onTrial = $client->request('GET', '/api/v1/billing/subscription', ['auth_bearer' => $this->tokenFor($client, 'snap-trial@plume.test')])->toArray();
        self::assertSame('trialing', $onTrial['status']);
        self::assertTrue($onTrial['entitled']);
        self::assertNotNull($onTrial['trialEndsAt']);
    }

    public function testPortalWithoutSubscriptionReturns409(): void
    {
        $this->createUser('noportal@plume.test'); // grandfathered, jamais payé → aucun client Stripe
        $client = static::createClient();
        $client->request('POST', '/api/v1/billing/portal', ['auth_bearer' => $this->tokenFor($client, 'noportal@plume.test')]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testWebhookActivatesThenTransitionsBySignedEvents(): void
    {
        $tenant = $this->createUser('webhook@plume.test');
        $subs = static::getContainer()->get(Subscriptions::class);
        \assert($subs instanceof Subscriptions);
        $clock = static::getContainer()->get(Clock::class);
        \assert($clock instanceof Clock);
        $secret = 'whsec_test_123';
        $controller = new StripeWebhookController($subs, $clock, $this->connection, $secret);

        // 1) checkout.session.completed (signé) → abonnement actif + client Stripe mémorisé.
        $completed = (string) json_encode(['type' => 'checkout.session.completed', 'data' => ['object' => [
            'client_reference_id' => $tenant, 'customer' => 'cus_wh', 'subscription' => 'sub_wh',
        ]]]);
        self::assertSame(200, $controller($this->signedRequest($completed, $secret))->getStatusCode());
        self::assertTrue($subs->isEntitled($tenant));
        self::assertSame('cus_wh', $subs->stripeCustomerFor($tenant));

        // 2) customer.subscription.updated past_due → lecture seule.
        $pastDue = (string) json_encode(['type' => 'customer.subscription.updated', 'data' => ['object' => [
            'customer' => 'cus_wh', 'status' => 'past_due',
        ]]]);
        self::assertSame(200, $controller($this->signedRequest($pastDue, $secret))->getStatusCode());
        self::assertFalse($subs->isEntitled($tenant));

        // 3) Signature invalide → 400, aucun effet.
        $bad = Request::create('/api/v1/billing/webhook', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $completed);
        $bad->headers->set('Stripe-Signature', 't='.time().',v1=deadbeef');
        self::assertSame(400, $controller($bad)->getStatusCode());
    }

    public function testWebhookRejectsStaleTimestamp(): void
    {
        // Anti-rejeu : un événement signé mais horodaté hors tolérance (300 s) est refusé (400).
        $subs = static::getContainer()->get(Subscriptions::class);
        \assert($subs instanceof Subscriptions);
        $clock = new FixedClock(new \DateTimeImmutable('2026-08-03 12:00:00'));
        $secret = 'whsec_stale';
        $controller = new StripeWebhookController($subs, $clock, $this->connection, $secret);

        $body = (string) json_encode(['id' => 'evt_stale', 'type' => 'checkout.session.completed', 'data' => ['object' => []]]);
        $staleTs = $clock->now()->getTimestamp() - 600; // 10 min avant l'horloge du contrôleur
        $request = Request::create('/api/v1/billing/webhook', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        $request->headers->set('Stripe-Signature', 't='.$staleTs.',v1='.hash_hmac('sha256', $staleTs.'.'.$body, $secret));

        self::assertSame(400, $controller($request)->getStatusCode());
    }

    public function testWebhookIgnoresReplayedEvent(): void
    {
        // Un event.id déjà traité est ignoré : rejouer le checkout après une résiliation ne re-crédite PAS l'accès.
        $tenant = $this->createUser('replay@plume.test');
        $subs = static::getContainer()->get(Subscriptions::class);
        \assert($subs instanceof Subscriptions);
        $clock = static::getContainer()->get(Clock::class);
        \assert($clock instanceof Clock);
        $secret = 'whsec_replay';
        $controller = new StripeWebhookController($subs, $clock, $this->connection, $secret);

        $completed = (string) json_encode(['id' => 'evt_1', 'type' => 'checkout.session.completed', 'data' => ['object' => [
            'client_reference_id' => $tenant, 'customer' => 'cus_r', 'subscription' => 'sub_r',
        ]]]);
        $controller($this->signedRequest($completed, $secret));
        self::assertTrue($subs->isEntitled($tenant));

        $deleted = (string) json_encode(['id' => 'evt_2', 'type' => 'customer.subscription.deleted', 'data' => ['object' => ['customer' => 'cus_r']]]);
        $controller($this->signedRequest($deleted, $secret));
        self::assertFalse($subs->isEntitled($tenant));

        // Rejeu de evt_1 (signature valide) : dédupliqué → l'accès reste coupé.
        $controller($this->signedRequest($completed, $secret));
        self::assertFalse($subs->isEntitled($tenant));
    }

    public function testWebhookRouteIsPublicAndEnforcesSignatureEndToEnd(): void
    {
        // Câblage de bout en bout via la VRAIE route HTTP : elle est PUBLIQUE (sinon 401) et bien
        // routée (sinon 404), et la signature est exigée → 400 sur signature invalide. Le chemin
        // succès (200) est couvert par les tests contrôleur-direct (secret connu). On ne committe
        // aucun secret webhook (gate gitleaks, dépôt public).
        $body = (string) json_encode(['id' => 'evt_http', 'type' => 'checkout.session.completed', 'data' => ['object' => []]]);

        static::createClient()->request('POST', '/api/v1/billing/webhook', [
            'headers' => ['Content-Type' => 'application/json', 'Stripe-Signature' => 't='.time().',v1=deadbeef'],
            'body' => $body,
        ]);
        self::assertResponseStatusCodeSame(400); // route atteinte, publique, signature refusée
    }

    public function testCheckoutAnnualPlanViaFakeGateway(): void
    {
        $this->createUser('annual@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'annual@plume.test');

        /** @var array{url: string} $checkout */
        $checkout = $client->request('POST', '/api/v1/billing/checkout', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['plan' => 'annual'],
        ])->toArray();
        self::assertStringContainsString('/settings', $checkout['url']);
    }

    public function testPortalSuccessWhenCustomerExists(): void
    {
        // Compte ayant payé (client Stripe présent) → le portail renvoie une URL (factice).
        $tenant = $this->createUser('portal@plume.test');
        $this->connection->executeStatement(
            "INSERT INTO subscription (tenant_id, status, stripe_customer_id, updated_at) VALUES (?, 'active', 'cus_portal', NOW())",
            [$tenant],
        );
        $client = static::createClient();

        /** @var array{url: string} $portal */
        $portal = $client->request('POST', '/api/v1/billing/portal', ['auth_bearer' => $this->tokenFor($client, 'portal@plume.test')])->toArray();
        self::assertStringContainsString('/settings', $portal['url']);
    }

    private function signedRequest(string $body, string $secret): Request
    {
        $t = time();
        $request = Request::create('/api/v1/billing/webhook', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: $body);
        $request->headers->set('Stripe-Signature', 't='.$t.',v1='.hash_hmac('sha256', $t.'.'.$body, $secret));

        return $request;
    }

    public function testEntitledAccountCanWrite(): void
    {
        // Compte grandfathered (aucun abonnement) → écriture produit autorisée.
        $this->createUser('active@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'active@plume.test');

        $client->request('POST', '/api/v1/organizations', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Éditions Ouvertes', 'type' => 'PUBLISHER'],
        ]);
        self::assertResponseStatusCodeSame(201);
    }
}
