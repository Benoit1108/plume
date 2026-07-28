<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Account\Infrastructure\Persistence\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Back-office : réservé à ROLE_ADMIN (403 pour une traductrice), vue d'ensemble en COMPTAGES,
 * liste des comptes (admins exclus, recherche), demande de suppression RGPD côté support
 * (soft-delete + révocation sessions + journal d'audit, jamais sur un compte admin).
 */
final class AdminApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens, organization, lead, audit_log RESTART IDENTITY CASCADE');

        $tokenLimiter = static::getContainer()->get('limiter.token_endpoints');
        \assert($tokenLimiter instanceof RateLimiterFactory);
        $tokenLimiter->create('127.0.0.1')->reset();
    }

    /** @param list<string> $roles */
    private function createUser(string $email, array $roles = []): string
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $tenant = Uuid::v7();
        $user = new User(Uuid::v7(), $tenant, $email);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $user->setRoles($roles);
        $em->persist($user);
        $em->flush();
        $em->clear();

        return $tenant->toRfc4122();
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

    public function testAdminRoutesAreForbiddenToRegularUsers(): void
    {
        $this->createUser('regular@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'regular@plume.test');

        $client->request('GET', '/api/v1/admin/overview', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/api/v1/admin/accounts', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testOverviewCountsAccountsAndBusiness(): void
    {
        $this->createUser('admin@plume.test', ['ROLE_ADMIN']);
        $tenant = $this->createUser('translator@plume.test');
        $this->connection->executeStatement(
            "INSERT INTO organization (id, tenant_id, name, type, working_languages, segments, do_not_contact, contacts)
             VALUES ('org-1', ?, 'Editions X', 'publisher', '[]', '[]', false, '[]')",
            [$tenant],
        );

        $client = static::createClient();
        $token = $this->tokenFor($client, 'admin@plume.test');
        $response = $client->request('GET', '/api/v1/admin/overview', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();

        /** @var array{accounts: array{total: int}, business: array{organizations: int}, queues: array<string, int>} $data */
        $data = $response->toArray();
        self::assertSame(1, $data['accounts']['total']); // l'admin n'est pas compté
        self::assertSame(1, $data['business']['organizations']);
        self::assertArrayHasKey('queues', $data);
    }

    public function testAccountsListExcludesAdminsAndSearches(): void
    {
        $this->createUser('admin@plume.test', ['ROLE_ADMIN']);
        $this->createUser('alice@plume.test');
        $this->createUser('bob@plume.test');

        $client = static::createClient();
        $token = $this->tokenFor($client, 'admin@plume.test');

        $response = $client->request('GET', '/api/v1/admin/accounts', ['auth_bearer' => $token]);
        /** @var array{accounts: list<array{email: string}>} $data */
        $data = $response->toArray();
        self::assertCount(2, $data['accounts']); // jamais l'admin
        self::assertSame(['alice@plume.test', 'bob@plume.test'], array_column($data['accounts'], 'email'));

        $response = $client->request('GET', '/api/v1/admin/accounts?q=ali', ['auth_bearer' => $token]);
        /** @var array{accounts: list<array{email: string}>} $filtered */
        $filtered = $response->toArray();
        self::assertCount(1, $filtered['accounts']);
        self::assertSame('alice@plume.test', $filtered['accounts'][0]['email']);
    }

    public function testSupportDeletionRequestSoftDeletesRevokesAndAudits(): void
    {
        $this->createUser('admin@plume.test', ['ROLE_ADMIN']);
        $tenant = $this->createUser('leaving@plume.test');

        $client = static::createClient();
        // La traductrice a une session ouverte (refresh token posé au login).
        $this->tokenFor($client, 'leaving@plume.test');
        $token = $this->tokenFor($client, 'admin@plume.test');

        $client->request('POST', '/api/v1/admin/accounts/'.$tenant.'/request-deletion', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseStatusCodeSame(204);

        // Soft-delete posé + sessions révoquées + audit tracé (acteur = l'admin).
        self::assertNotNull($this->connection->fetchOne('SELECT deletion_requested_at FROM app_user WHERE tenant_id = ?', [$tenant]));
        $tokens = $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens WHERE username = ?', ['leaving@plume.test']);
        self::assertSame(0, is_numeric($tokens) ? (int) $tokens : -1);
        /** @var array{actor: string, action: string, target: string} $audit */
        $audit = $this->connection->fetchAssociative('SELECT actor, action, target FROM audit_log ORDER BY occurred_at DESC LIMIT 1');
        self::assertSame('admin@plume.test', $audit['actor']);
        self::assertSame('admin.account_deletion_requested', $audit['action']);
        self::assertSame($tenant, $audit['target']);

        // La traductrice ne peut plus se connecter ; un compte admin n'est pas supprimable (404).
        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'leaving@plume.test', 'password' => self::PASSWORD]]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testAdminAccountCannotBeDeletedThroughSupportRoute(): void
    {
        $adminTenant = $this->createUser('admin@plume.test', ['ROLE_ADMIN']);
        $client = static::createClient();
        $token = $this->tokenFor($client, 'admin@plume.test');

        $client->request('POST', '/api/v1/admin/accounts/'.$adminTenant.'/request-deletion', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseStatusCodeSame(404);
    }
}
