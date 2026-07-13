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
 * Tests fonctionnels de l'API Répertoire contre une vraie base Postgres :
 * authentification, CRUD, ISOLATION TENANT, mapping des erreurs métier, import.
 * (Migrations exécutées en amont par `make test` / la CI.)
 */
final class DirectoryApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE organization, lead, app_user, refresh_tokens RESTART IDENTITY CASCADE');
    }

    private function createUser(string $email): void
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $user = new User(Uuid::v7(), Uuid::v7(), $email);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);
        $em->flush();
        $em->clear();
    }

    private function tokenFor(Client $client, string $email): string
    {
        $response = $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => $email, 'password' => self::PASSWORD],
        ]);
        self::assertResponseIsSuccessful();

        /** @var array{token: string} $data */
        $data = $response->toArray();

        return $data['token'];
    }

    /** @param array<string, mixed> $json */
    private function post(Client $client, string $token, string $url, array $json): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $client->request('POST', $url, [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $json,
        ]);
    }

    public function testLoginIssuesTokenPair(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();

        $response = $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => 'a@plume.test', 'password' => self::PASSWORD],
        ]);

        self::assertResponseIsSuccessful();
        $data = $response->toArray();
        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('refresh_token', $data);
    }

    public function testApiRequiresAuthentication(): void
    {
        static::createClient()->request('GET', '/api/v1/organizations');

        self::assertResponseStatusCodeSame(401);
    }

    public function testOrganizationCrudLifecycle(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // POST
        $response = $this->post($client, $token, '/api/v1/organizations', [
            'name' => 'Actes Sud',
            'type' => 'PUBLISHER',
            'country' => 'FR',
            'workingLanguages' => ['en', 'fr'],
            'segments' => ['PUBLISHING'],
        ]);
        self::assertResponseStatusCodeSame(201);
        /** @var array{id: string} $created */
        $created = $response->toArray();

        // GET item
        $client->request('GET', '/api/v1/organizations/'.$created['id'], ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['name' => 'Actes Sud', 'country' => 'FR']);

        // PATCH
        $client->request('PATCH', '/api/v1/organizations/'.$created['id'], [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['notes' => 'à relancer'],
        ]);
        self::assertResponseIsSuccessful();

        // GET collection paginée
        $response = $client->request('GET', '/api/v1/organizations?itemsPerPage=10', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);
        self::assertResponseIsSuccessful();
        $collection = $response->toArray();
        self::assertSame(1, $collection['totalItems'] ?? $collection['hydra:totalItems'] ?? null);
    }

    public function testTenantIsolation(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();
        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $tokenB = $this->tokenFor($client, 'b@plume.test');

        $response = $this->post($client, $tokenA, '/api/v1/organizations', ['name' => 'Secret A', 'type' => 'OTHER']);
        self::assertResponseStatusCodeSame(201);
        /** @var array{id: string} $created */
        $created = $response->toArray();

        // B ne voit rien dans la liste…
        $response = $client->request('GET', '/api/v1/organizations', [
            'auth_bearer' => $tokenB,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);
        $collection = $response->toArray();
        self::assertSame(0, $collection['totalItems'] ?? $collection['hydra:totalItems'] ?? null);

        // …ni en accès direct par id.
        $client->request('GET', '/api/v1/organizations/'.$created['id'], ['auth_bearer' => $tokenB]);
        self::assertResponseStatusCodeSame(404);

        // …et ne peut pas la modifier.
        $client->request('PATCH', '/api/v1/organizations/'.$created['id'], [
            'auth_bearer' => $tokenB,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['name' => 'Piraté'],
        ]);
        self::assertResponseStatusCodeSame(404);

        // B peut créer une organisation du même nom (unicité PAR tenant).
        $this->post($client, $tokenB, '/api/v1/organizations', ['name' => 'Secret A', 'type' => 'OTHER']);
        self::assertResponseStatusCodeSame(201);
    }

    public function testDuplicateOrganizationNameIsConflict(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $this->post($client, $token, '/api/v1/organizations', ['name' => 'Actes Sud', 'type' => 'PUBLISHER']);
        self::assertResponseStatusCodeSame(201);

        $this->post($client, $token, '/api/v1/organizations', ['name' => '  actes SUD ', 'type' => 'OTHER']);
        self::assertResponseStatusCodeSame(409);
    }

    public function testContactLifecycleAndDuplicateEmailConflict(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $response = $this->post($client, $token, '/api/v1/organizations', ['name' => 'Actes Sud', 'type' => 'PUBLISHER']);
        /** @var array{id: string} $org */
        $org = $response->toArray();

        // Ajout
        $response = $this->post($client, $token, '/api/v1/organizations/'.$org['id'].'/contacts', [
            'fullName' => 'Claire Martin',
            'email' => 'claire@actes-sud.fr',
        ]);
        self::assertResponseStatusCodeSame(201);
        /** @var array{id: string} $contact */
        $contact = $response->toArray();

        // Doublon d'email → 409 (mapping Conflict)
        $this->post($client, $token, '/api/v1/organizations/'.$org['id'].'/contacts', [
            'fullName' => 'Autre Personne',
            'email' => 'CLAIRE@actes-sud.fr',
        ]);
        self::assertResponseStatusCodeSame(409);

        // Email invalide → 422 (validation)
        $this->post($client, $token, '/api/v1/organizations/'.$org['id'].'/contacts', [
            'fullName' => 'X',
            'email' => 'pas-un-email',
        ]);
        self::assertResponseStatusCodeSame(422);

        // PATCH puis DELETE
        $client->request('PATCH', '/api/v1/organizations/'.$org['id'].'/contacts/'.$contact['id'], [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['role' => 'Éditrice'],
        ]);
        self::assertResponseIsSuccessful();

        $client->request('DELETE', '/api/v1/organizations/'.$org['id'].'/contacts/'.$contact['id'], ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testDoNotContactIsReversible(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $response = $this->post($client, $token, '/api/v1/organizations', ['name' => 'Nectarine', 'type' => 'AV_STUDIO']);
        /** @var array{id: string} $org */
        $org = $response->toArray();

        foreach ([true, false] as $flag) {
            $client->request('PATCH', '/api/v1/organizations/'.$org['id'], [
                'auth_bearer' => $token,
                'headers' => ['Content-Type' => 'application/merge-patch+json'],
                'json' => ['doNotContact' => $flag],
            ]);
            self::assertResponseIsSuccessful();

            $response = $client->request('GET', '/api/v1/organizations/'.$org['id'], ['auth_bearer' => $token]);
            self::assertSame($flag, $response->toArray()['doNotContact']);
        }
    }

    public function testCsvImportDedupsAndReports(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $this->post($client, $token, '/api/v1/organizations', ['name' => 'Actes Sud', 'type' => 'PUBLISHER']);

        $csv = "nom;type;contact;email\nActes Sud;Éditeur;;\nStudio VF;Labo A/V;Marie Dupont;marie@studiovf.fr\n;Agence;Sans Nom;x@y.fr\n";
        $response = $this->post($client, $token, '/api/v1/organizations/import', ['content' => $csv]);

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['imported' => 1, 'skipped' => 1, 'failed' => 1]);

        $response = $client->request('GET', '/api/v1/organizations?q=Studio', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);
        $collection = $response->toArray();
        /** @var list<array{contacts: list<array{fullName: string}>}> $members */
        $members = $collection['member'] ?? $collection['hydra:member'] ?? [];
        self::assertCount(1, $members);
        self::assertSame('Marie Dupont', $members[0]['contacts'][0]['fullName'] ?? null);
    }
}
