<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Account\Infrastructure\Scheduler\PurgeAccount;
use App\Account\Infrastructure\Scheduler\PurgeExpiredDemosHandler;
use App\Account\Infrastructure\Scheduler\PurgeExpiredDemosTick;
use App\Drafting\Application\AiGenerationPolicy;
use App\Shared\Domain\ValueObject\TenantId;
use App\Shared\Infrastructure\Doctrine\Tenancy\TenantScope;
use App\Tests\Support\FixedClock;
use Doctrine\DBAL\Connection;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Vitrine — compte démo éphémère : « Essayer la démo » crée un tenant isolé pré-rempli et connecte
 * la visiteuse ; un tick purge les démos expirées (réutilise la purge RGPD).
 */
final class DemoApiTest extends ApiTestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens, organization, lead, interaction, subscription RESTART IDENTITY CASCADE');

        $limiter = static::getContainer()->get('limiter.demo');
        \assert($limiter instanceof RateLimiterFactory);
        $limiter->create('127.0.0.1')->reset();
    }

    public function testEnterDemoCreatesSeededEphemeralAccount(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/v1/demo', ['json' => []]);
        self::assertResponseIsSuccessful(); // login direct : cookies JWT posés par la réponse

        /** @var array{tenant_id: string, demo_expires_at: ?string, roles: string} $row */
        $row = $this->connection->fetchAssociative("SELECT tenant_id, demo_expires_at, roles::text AS roles FROM app_user WHERE email LIKE 'demo-%@demo.plume.local'");
        self::assertNotNull($row['demo_expires_at']); // marqué éphémère
        self::assertStringContainsString('ROLE_DEMO', $row['roles']);

        // Données fictives semées (le produit est « vivant »).
        $orgs = $this->connection->fetchOne('SELECT COUNT(*) FROM organization WHERE tenant_id = ?', [$row['tenant_id']]);
        $leads = $this->connection->fetchOne('SELECT COUNT(*) FROM lead WHERE tenant_id = ?', [$row['tenant_id']]);
        self::assertSame(3, is_numeric($orgs) ? (int) $orgs : 0);
        self::assertSame(3, is_numeric($leads) ? (int) $leads : 0);
    }

    public function testPurgeExpiredDemosDispatchesPurgeForExpiredOnly(): void
    {
        $expired = $this->seedDemoUser('old@demo.plume.local', '-1 hour');
        $this->seedDemoUser('fresh@demo.plume.local', '+2 hours'); // pas encore expiré

        $bus = new class implements MessageBusInterface {
            /** @var list<object> */
            public array $dispatched = [];

            public function dispatch(object $message, array $stamps = []): Envelope
            {
                $this->dispatched[] = $message;

                return new Envelope($message);
            }
        };

        (new PurgeExpiredDemosHandler($this->connection, new FixedClock(new \DateTimeImmutable('now')), $bus))(new PurgeExpiredDemosTick());

        self::assertCount(1, $bus->dispatched);
        self::assertInstanceOf(PurgeAccount::class, $bus->dispatched[0]);
        self::assertSame($expired, $bus->dispatched[0]->tenantId);
    }

    public function testReturns503WhenGlobalDemoCapReached(): void
    {
        // Sature le plafond global (MAX_ACTIVE_DEMOS = 50) de démos actives.
        for ($i = 0; $i < 50; ++$i) {
            $this->seedDemoUser("cap-$i@demo.plume.local", '+2 hours');
        }

        $client = static::createClient();
        $client->request('POST', '/api/v1/demo', ['json' => []]);
        self::assertResponseStatusCodeSame(503); // demo_unavailable, le temps que la purge horaire libère
    }

    public function testPaidGenerationRefusedForDemoTenant(): void
    {
        $tenant = $this->seedDemoUser('policy@demo.plume.local', '+2 hours');
        $this->activate($tenant);

        self::assertFalse($this->policy()->allowsPaidGeneration()); // démo → repli canned gratuit
    }

    /**
     * Revue SEC-P2b : la session démo est ouverte à un VISITEUR ANONYME. Elle ne doit pas pouvoir
     * enregistrer une URL arbitraire ni la faire relever par le serveur (requête sortante émise par
     * Plume vers l'hôte choisi). La LECTURE de l'écran Sources, elle, reste ouverte : c'est ce qu'on
     * montre en démonstration.
     */
    public function testDemoSessionCannotReachOutwardThroughSources(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/demo', ['json' => []]); // pose les cookies de session démo
        self::assertResponseIsSuccessful();

        $response = $client->request('POST', '/api/v1/sources', ['json' => ['source' => 'RSS', 'url' => 'https://exfil.example/rss']]);
        self::assertSame(403, $response->getStatusCode());
        self::assertStringContainsString('demo_restricted', $response->getContent(false));

        $client->request('POST', '/api/v1/sources/poll', ['json' => []]);
        self::assertResponseStatusCodeSame(403);

        $client->request('DELETE', '/api/v1/sources/00000000-0000-0000-0000-000000000000');
        self::assertResponseStatusCodeSame(403);

        // Lecture : toujours permise (l'écran Réglages « Sources » reste visitable).
        $client->request('GET', '/api/v1/sources');
        self::assertResponseIsSuccessful();
    }

    public function testPaidGenerationAllowedForRegularTenant(): void
    {
        $tenant = Uuid::v7()->toRfc4122();
        $this->connection->executeStatement(
            "INSERT INTO app_user (id, tenant_id, email, password, roles, email_verified)
             VALUES (?, ?, 'reg@plume.test', 'x', '[\"ROLE_USER\"]', true)",
            [Uuid::v7()->toRfc4122(), $tenant],
        );
        $this->activate($tenant);

        self::assertTrue($this->policy()->allowsPaidGeneration()); // compte normal → génération payante possible
    }

    private function policy(): AiGenerationPolicy
    {
        $policy = static::getContainer()->get(AiGenerationPolicy::class);
        \assert($policy instanceof AiGenerationPolicy);

        return $policy;
    }

    private function activate(string $tenantId): void
    {
        $scope = static::getContainer()->get(TenantScope::class);
        \assert($scope instanceof TenantScope);
        $scope->activate(TenantId::fromString($tenantId));
    }

    private function seedDemoUser(string $email, string $expiresModifier): string
    {
        $tenant = Uuid::v7()->toRfc4122();
        $this->connection->executeStatement(
            "INSERT INTO app_user (id, tenant_id, email, password, roles, email_verified, demo_expires_at)
             VALUES (?, ?, ?, 'x', '[\"ROLE_DEMO\"]', true, ?)",
            [Uuid::v7()->toRfc4122(), $tenant, $email, (new \DateTimeImmutable($expiresModifier))->format('Y-m-d H:i:s')],
        );

        return $tenant;
    }
}
