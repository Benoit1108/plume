<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Account\Infrastructure\Persistence\User;
use App\Account\Infrastructure\Security\TotpService;
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

    /** Secret TOTP connu (Base32 valide) : les admins ont la 2FA obligatoire. */
    private const ADMIN_TOTP_SECRET = 'JBSWY3DPEHPK3PXP';

    /** @param list<string> $roles */
    private function createUser(string $email, array $roles = [], ?string $totpSecret = null): string
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

        if (null !== $totpSecret) {
            $this->connection->executeStatement('UPDATE app_user SET totp_secret = ? WHERE tenant_id = ?', [$totpSecret, $tenant->toRfc4122()]);
        }

        return $tenant->toRfc4122();
    }

    /** Crée un admin avec 2FA (obligatoire pour le back-office). */
    private function createAdmin(string $email = 'admin@plume.test'): string
    {
        return $this->createUser($email, ['ROLE_ADMIN'], self::ADMIN_TOTP_SECRET);
    }

    private function tokenFor(Client $client, string $email, ?string $otpSecret = null): string
    {
        $body = ['email' => $email, 'password' => self::PASSWORD];
        if (null !== $otpSecret) {
            $totp = static::getContainer()->get(TotpService::class);
            \assert($totp instanceof TotpService);
            $body['otp'] = $totp->currentCode($otpSecret);
        }

        /** @var array{token: string} $data */
        $data = $client->request('POST', '/api/v1/login_check', ['json' => $body])->toArray();

        return $data['token'];
    }

    /** Token d'un admin (login avec OTP calculé depuis le secret connu). */
    private function adminToken(Client $client, string $email = 'admin@plume.test'): string
    {
        return $this->tokenFor($client, $email, self::ADMIN_TOTP_SECRET);
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
        $this->createAdmin();
        $tenant = $this->createUser('translator@plume.test');
        $this->connection->executeStatement(
            "INSERT INTO organization (id, tenant_id, name, type, working_languages, segments, do_not_contact, contacts)
             VALUES ('org-1', ?, 'Editions X', 'publisher', '[]', '[]', false, '[]')",
            [$tenant],
        );

        $client = static::createClient();
        $token = $this->adminToken($client);
        $response = $client->request('GET', '/api/v1/admin/overview', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();

        /** @var array{accounts: array{total: int}, business: array{organizations: int}, queues: array<string, int>} $data */
        $data = $response->toArray();
        self::assertSame(1, $data['accounts']['total']); // l'admin n'est pas compté
        self::assertSame(1, $data['business']['organizations']);
        self::assertArrayHasKey('queues', $data);
    }

    public function testSystemStatusReportsOperationalHealth(): void
    {
        $this->createAdmin();
        $client = static::createClient();
        $token = $this->adminToken($client);

        $response = $client->request('GET', '/api/v1/admin/status', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();

        /** @var array{db: string, queues: array<string, int>, failed: int, backlogAgeSeconds: int, mailboxesError: int} $data */
        $data = $response->toArray();
        self::assertSame('ok', $data['db']);
        self::assertSame(0, $data['mailboxesError']);
        self::assertArrayHasKey('failed', $data);
        self::assertArrayHasKey('backlogAgeSeconds', $data);
    }

    public function testSystemStatusIsForbiddenToRegularUsers(): void
    {
        $this->createUser('regular@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'regular@plume.test');
        $client->request('GET', '/api/v1/admin/status', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/api/v1/admin/metrics', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testMetricsReportsAccountsAndSignups(): void
    {
        // Base de test partagée : on isole le signal d'activité (sinon des interactions d'autres tests
        // fausseraient active30d).
        $this->connection->executeStatement('TRUNCATE TABLE interaction');
        $this->createAdmin();
        $this->createUser('translator@plume.test'); // vérifié par défaut, créé « maintenant »

        $client = static::createClient();
        $response = $client->request('GET', '/api/v1/admin/metrics', ['auth_bearer' => $this->adminToken($client)]);
        self::assertResponseIsSuccessful();

        /** @var array{accounts: array{total: int, verified: int, active30d: int}, signups: list<array{week: string, count: int}>, leadsByStatus: array<string, int>, totals: array{leads: int}} $data */
        $data = $response->toArray();
        self::assertSame(1, $data['accounts']['total']); // l'admin est exclu
        self::assertSame(1, $data['accounts']['verified']);
        self::assertSame(0, $data['accounts']['active30d']); // aucune interaction seedée
        // La traductrice vient d'être créée → 1 inscription cette semaine.
        self::assertSame(1, array_sum(array_map(static fn (array $w): int => $w['count'], $data['signups'])));
        self::assertArrayHasKey('leadsByStatus', $data);
    }

    public function testAccountsListExcludesAdminsAndSearches(): void
    {
        $this->createAdmin();
        $this->createUser('alice@plume.test');
        $this->createUser('bob@plume.test');

        $client = static::createClient();
        $token = $this->adminToken($client);

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
        $this->createAdmin();
        $tenant = $this->createUser('leaving@plume.test');

        $client = static::createClient();
        // La traductrice a une session ouverte (refresh token posé au login).
        $this->tokenFor($client, 'leaving@plume.test');
        $token = $this->adminToken($client);

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

    public function testResetTwoFactorDisablesItAndAudits(): void
    {
        $this->createAdmin();
        $tenant = $this->createUser('locked@plume.test');
        // La traductrice a la 2FA active (secret + codes de secours en base).
        $this->connection->executeStatement(
            "UPDATE app_user SET totp_secret = 'SECRET', backup_codes = '[\"h\"]' WHERE tenant_id = ?",
            [$tenant],
        );

        $client = static::createClient();
        $token = $this->adminToken($client);
        $client->request('POST', '/api/v1/admin/accounts/'.$tenant.'/reset-2fa', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseStatusCodeSame(204);

        self::assertNull($this->connection->fetchOne('SELECT totp_secret FROM app_user WHERE tenant_id = ?', [$tenant]));
        /** @var array{actor: string, action: string} $audit */
        $audit = $this->connection->fetchAssociative('SELECT actor, action FROM audit_log ORDER BY occurred_at DESC LIMIT 1');
        self::assertSame('admin.2fa_reset', $audit['action']);
        self::assertSame('admin@plume.test', $audit['actor']);
    }

    public function testAdminAccountCannotBeDeletedThroughSupportRoute(): void
    {
        $adminTenant = $this->createAdmin();
        $client = static::createClient();
        $token = $this->adminToken($client);

        $client->request('POST', '/api/v1/admin/accounts/'.$adminTenant.'/request-deletion', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminWithoutTwoFactorIsBlocked(): void
    {
        // Admin SANS 2FA (pas de secret) : peut se connecter, mais le back-office exige la 2FA.
        $this->createUser('noadmin2fa@plume.test', ['ROLE_ADMIN']);
        $client = static::createClient();
        $token = $this->tokenFor($client, 'noadmin2fa@plume.test'); // pas d'OTP : pas de 2FA active

        $client->request('GET', '/api/v1/admin/overview', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(403);
    }
}
