<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Account\Infrastructure\Persistence\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Uid\Uuid;

/**
 * Mot de passe oublié (V2.1a) : demande anti-énumération (toujours 204, jeton créé seulement pour un
 * compte existant) + réinitialisation par jeton (mot de passe changé, sessions révoquées, jeton
 * consommé ; jeton expiré/invalide → 422).
 */
final class ForgotPasswordApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens, password_reset_token RESTART IDENTITY CASCADE');

        foreach (['limiter.password_reset', 'limiter.token_endpoints'] as $id) {
            $factory = static::getContainer()->get($id);
            \assert($factory instanceof RateLimiterFactory);
            $factory->create('127.0.0.1')->reset();
        }
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

    private function seedResetToken(string $email, string $rawToken, string $expiresAt): void
    {
        $this->connection->executeStatement(
            'INSERT INTO password_reset_token (id, email, token_hash, expires_at, created_at) VALUES (?, ?, ?, ?, ?)',
            [Uuid::v7()->toRfc4122(), $email, hash('sha256', $rawToken), $expiresAt, '2026-07-28 10:00:00'],
        );
    }

    private function tokenCount(string $email): int
    {
        $value = $this->connection->fetchOne('SELECT COUNT(*) FROM password_reset_token WHERE email = ?', [$email]);

        return is_numeric($value) ? (int) $value : -1;
    }

    public function testForgotCreatesTokenForKnownEmailAndAlwaysReturns204(): void
    {
        $this->createUser('known@plume.test');
        $client = static::createClient();

        $client->request('POST', '/api/v1/account/password/forgot', ['json' => ['email' => 'known@plume.test']]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(1, $this->tokenCount('known@plume.test'));

        // Email inconnu : même 204 (anti-énumération), aucun jeton.
        $client->request('POST', '/api/v1/account/password/forgot', ['json' => ['email' => 'nobody@plume.test']]);
        self::assertResponseStatusCodeSame(204);
        self::assertSame(0, $this->tokenCount('nobody@plume.test'));
    }

    public function testResetWithValidTokenChangesPasswordRevokesSessionsAndConsumesToken(): void
    {
        $this->createUser('reset@plume.test');
        $this->seedResetToken('reset@plume.test', 'valid-raw-token', '2999-01-01 00:00:00');
        $client = static::createClient();

        // Ouvre une session (pose un refresh token) pour vérifier sa révocation.
        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'reset@plume.test', 'password' => self::PASSWORD]]);
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/v1/account/password/reset', [
            'json' => ['token' => 'valid-raw-token', 'newPassword' => 'secret-Test-NEW'],
        ]);
        self::assertResponseStatusCodeSame(204);

        // Jeton consommé + session révoquée — vérifié AVANT toute reconnexion (qui recréerait un refresh).
        self::assertSame(0, $this->tokenCount('reset@plume.test'));
        $refresh = $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens WHERE username = ?', ['reset@plume.test']);
        self::assertSame(0, is_numeric($refresh) ? (int) $refresh : -1);

        // Nouveau mot de passe OK, ancien KO.
        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'reset@plume.test', 'password' => self::PASSWORD]]);
        self::assertResponseStatusCodeSame(401);
        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'reset@plume.test', 'password' => 'secret-Test-NEW']]);
        self::assertResponseIsSuccessful();
    }

    public function testResetWithExpiredTokenIsRejectedAndPurged(): void
    {
        $this->createUser('exp@plume.test');
        $this->seedResetToken('exp@plume.test', 'expired-raw-token', '2020-01-01 00:00:00');
        $client = static::createClient();

        $client->request('POST', '/api/v1/account/password/reset', [
            'json' => ['token' => 'expired-raw-token', 'newPassword' => 'secret-Test-NEW'],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->tokenCount('exp@plume.test')); // jeton expiré purgé
    }

    public function testResetWithUnknownTokenIsRejected(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/account/password/reset', [
            'json' => ['token' => 'nope', 'newPassword' => 'secret-Test-NEW'],
        ]);
        self::assertResponseStatusCodeSame(422);
    }
}
