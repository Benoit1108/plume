<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Account\Infrastructure\Persistence\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * File de tri (Sourcing) : liste des annonces PENDING, décisions accepter /
 * fusionner / rejeter (promotion cross-contexte), isolation tenant.
 */
final class CandidateLeadApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE candidate_lead, raw_alert, lead, organization, app_user, refresh_tokens RESTART IDENTITY CASCADE');
    }

    private function createUser(string $email): string
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $tenantId = Uuid::v7();
        $user = new User(Uuid::v7(), $tenantId, $email);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);
        $em->flush();
        $em->clear();

        return $tenantId->toRfc4122();
    }

    private function seedCandidate(string $tenantId, string $id): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->insert('candidate_lead', [
            'id' => $id,
            'tenant_id' => $tenantId,
            'source' => 'PROZ',
            'dedup_hash' => 'hash-'.$id,
            'status' => 'PENDING',
            'title' => 'Traduction littéraire EN>FR',
            'organization_name' => 'Éditions Test',
            'language_pair' => 'en>fr',
            'ingested_at' => '2026-07-15 10:00:00',
        ]);
    }

    private function tokenFor(Client $client, string $email): string
    {
        $response = $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => $email, 'password' => self::PASSWORD],
        ]);

        /** @var array{token: string} $data */
        $data = $response->toArray();

        return $data['token'];
    }

    /**
     * @param array<mixed> $collection
     *
     * @return list<array<string, mixed>>
     */
    private function membersOf(array $collection): array
    {
        /** @var list<array<string, mixed>> $members */
        $members = $collection['member'] ?? $collection['hydra:member'] ?? [];

        return $members;
    }

    public function testListsPendingQueue(): void
    {
        $tenant = $this->createUser('a@plume.test');
        $this->seedCandidate($tenant, 'cand-1');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $data = $client->request('GET', '/api/v1/candidate-leads', ['auth_bearer' => $token])->toArray();

        $members = $this->membersOf($data);
        self::assertCount(1, $members);
        self::assertSame('Traduction littéraire EN>FR', $members[0]['title']);
        self::assertSame('PROZ', $members[0]['source']);
    }

    public function testPollIngestsFromTheConfiguredSourceAndIsIdempotent(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Sans SOURCING_RSS_FEED_URL en test => FakeAlertSource (2 annonces démo).
        $client->request('POST', '/api/v1/sources/poll', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(202);

        $members = $this->membersOf($client->request('GET', '/api/v1/candidate-leads', ['auth_bearer' => $token])->toArray());
        self::assertCount(2, $members);

        // Re-relève : mêmes externalId (guid) => dédoublonné, aucun doublon.
        $client->request('POST', '/api/v1/sources/poll', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(202);
        $members = $this->membersOf($client->request('GET', '/api/v1/candidate-leads', ['auth_bearer' => $token])->toArray());
        self::assertCount(2, $members);
    }

    public function testPollIsIsolatedPerTenant(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();

        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $client->request('POST', '/api/v1/sources/poll', ['auth_bearer' => $tokenA]);

        // B n'a rien relevé : sa file reste vide (les annonces de A ne fuitent pas).
        $tokenB = $this->tokenFor($client, 'b@plume.test');
        $members = $this->membersOf($client->request('GET', '/api/v1/candidate-leads', ['auth_bearer' => $tokenB])->toArray());
        self::assertCount(0, $members);
    }

    public function testAcceptCreatesOrganizationAndLeadAndLeavesQueue(): void
    {
        $tenant = $this->createUser('a@plume.test');
        $this->seedCandidate($tenant, 'cand-1');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $client->request('POST', '/api/v1/candidate-leads/cand-1/accept', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => [
                'organizationName' => 'Éditions Test',
                'organizationType' => 'PUBLISHER',
                'languagePair' => 'en>fr',
                'segment' => 'PUBLISHING',
                'priority' => 'MEDIUM',
            ],
        ]);
        self::assertResponseStatusCodeSame(204);

        // La piste existe, avec la provenance fine.
        $leads = $this->membersOf($client->request('GET', '/api/v1/leads', ['auth_bearer' => $token])->toArray());
        self::assertCount(1, $leads);
        self::assertSame('PROZ', $leads[0]['source']);

        // La candidate a quitté la file.
        $queue = $this->membersOf($client->request('GET', '/api/v1/candidate-leads', ['auth_bearer' => $token])->toArray());
        self::assertCount(0, $queue);
    }

    public function testMergeAttachesToExistingOrganization(): void
    {
        $tenant = $this->createUser('a@plume.test');
        $this->seedCandidate($tenant, 'cand-1');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Une organisation existe déjà.
        $org = $client->request('POST', '/api/v1/organizations', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Éditions Existantes', 'type' => 'PUBLISHER'],
        ])->toArray();
        $orgId = $org['id'];

        $client->request('POST', '/api/v1/candidate-leads/cand-1/merge', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['organizationId' => $orgId, 'languagePair' => 'en>fr', 'segment' => 'PUBLISHING', 'priority' => 'MEDIUM'],
        ]);
        self::assertResponseStatusCodeSame(204);

        $leads = $this->membersOf($client->request('GET', '/api/v1/leads', ['auth_bearer' => $token])->toArray());
        self::assertCount(1, $leads);
        self::assertSame($orgId, $leads[0]['organizationId']);
    }

    public function testRejectRemovesFromQueue(): void
    {
        $tenant = $this->createUser('a@plume.test');
        $this->seedCandidate($tenant, 'cand-1');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $client->request('POST', '/api/v1/candidate-leads/cand-1/reject', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(204);

        $queue = $this->membersOf($client->request('GET', '/api/v1/candidate-leads', ['auth_bearer' => $token])->toArray());
        self::assertCount(0, $queue);
    }

    public function testQueueIsIsolatedPerTenant(): void
    {
        $tenantA = $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $this->seedCandidate($tenantA, 'cand-1');
        $client = static::createClient();

        $tokenB = $this->tokenFor($client, 'b@plume.test');
        $queue = $this->membersOf($client->request('GET', '/api/v1/candidate-leads', ['auth_bearer' => $tokenB])->toArray());

        self::assertCount(0, $queue);
    }

    public function testMutationsAreIsolatedPerTenant(): void
    {
        $tenantA = $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $this->seedCandidate($tenantA, 'cand-1');
        $client = static::createClient();
        $tokenB = $this->tokenFor($client, 'b@plume.test');

        // B ne voit pas la candidate de A → 404 sur chaque mutation (isolation fail-closed).
        $client->request('POST', '/api/v1/candidate-leads/cand-1/reject', ['auth_bearer' => $tokenB]);
        self::assertResponseStatusCodeSame(404);

        $client->request('POST', '/api/v1/candidate-leads/cand-1/accept', [
            'auth_bearer' => $tokenB,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['organizationName' => 'X', 'organizationType' => 'PUBLISHER', 'languagePair' => 'en>fr', 'segment' => 'PUBLISHING', 'priority' => 'MEDIUM'],
        ]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDoubleAcceptReturns409(): void
    {
        $tenant = $this->createUser('a@plume.test');
        $this->seedCandidate($tenant, 'cand-1');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $body = [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['organizationName' => 'Éditions Test', 'organizationType' => 'PUBLISHER', 'languagePair' => 'en>fr', 'segment' => 'PUBLISHING', 'priority' => 'MEDIUM'],
        ];

        $client->request('POST', '/api/v1/candidate-leads/cand-1/accept', $body);
        self::assertResponseStatusCodeSame(204);

        // Re-tri (double-clic / redélivrance) → 409 (garde CandidateAlreadyTriaged).
        $client->request('POST', '/api/v1/candidate-leads/cand-1/accept', $body);
        self::assertResponseStatusCodeSame(409);
    }

    public function testMergeIntoOrgWithActiveLeadAttachesWithoutSecondLead(): void
    {
        $tenant = $this->createUser('a@plume.test');
        $this->seedCandidate($tenant, 'cand-1');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Organisation avec une piste active.
        $org = $client->request('POST', '/api/v1/organizations', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['name' => 'Éditions Actives', 'type' => 'PUBLISHER'],
        ])->toArray();
        $orgId = $org['id'];
        $client->request('POST', '/api/v1/leads', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['organizationId' => $orgId, 'languagePair' => 'en>fr', 'source' => 'DIRECT', 'priority' => 'MEDIUM', 'segment' => 'PUBLISHING'],
        ]);
        self::assertResponseIsSuccessful();

        // Fusion : rattache à la piste active — pas de seconde piste.
        $client->request('POST', '/api/v1/candidate-leads/cand-1/merge', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['organizationId' => $orgId, 'languagePair' => 'en>fr', 'segment' => 'PUBLISHING', 'priority' => 'MEDIUM'],
        ]);
        self::assertResponseStatusCodeSame(204);

        $leads = $this->membersOf($client->request('GET', '/api/v1/leads', ['auth_bearer' => $token])->toArray());
        self::assertCount(1, $leads);
    }

    public function testRejectUnknownCandidateReturns404(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $client->request('POST', '/api/v1/candidate-leads/nope/reject', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(404);
    }
}
