<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Account\Infrastructure\Persistence\User;
use App\Billing\Infrastructure\Persistence\DoctrineSubscriptions;
use App\Tests\Support\FixedClock;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
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
        $this->connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens, subscription, organization, lead, profile RESTART IDENTITY CASCADE');

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
