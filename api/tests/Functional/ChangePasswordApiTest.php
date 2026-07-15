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
 * Changement de mot de passe (page Compte) : exige l'ancien mot de passe,
 * rejette les invalides, limite le débit par utilisateur.
 */
final class ChangePasswordApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens RESTART IDENTITY CASCADE');
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

    private function tokenFor(Client $client, string $email, string $password = self::PASSWORD): string
    {
        $response = $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => $email, 'password' => $password],
        ]);

        /** @var array{token: string} $data */
        $data = $response->toArray();

        return $data['token'];
    }

    public function testChangesPasswordThenLoginUsesTheNewOne(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $client->request('POST', '/api/v1/account/password', [
            'auth_bearer' => $token,
            'json' => ['currentPassword' => self::PASSWORD, 'newPassword' => 'nouveau-Mdp-456'],
        ]);
        self::assertResponseStatusCodeSame(204);

        // L'ancien mot de passe ne fonctionne plus, le nouveau oui.
        $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => 'a@plume.test', 'password' => self::PASSWORD],
        ]);
        self::assertResponseStatusCodeSame(401);

        $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => 'a@plume.test', 'password' => 'nouveau-Mdp-456'],
        ]);
        self::assertResponseIsSuccessful();
    }

    public function testRejectsWrongCurrentPassword(): void
    {
        $this->createUser('b@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'b@plume.test');

        $client->request('POST', '/api/v1/account/password', [
            'auth_bearer' => $token,
            'json' => ['currentPassword' => 'wrong-password', 'newPassword' => 'nouveau-Mdp-456'],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testRejectsTooShortNewPassword(): void
    {
        $this->createUser('c@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'c@plume.test');

        $client->request('POST', '/api/v1/account/password', [
            'auth_bearer' => $token,
            'json' => ['currentPassword' => self::PASSWORD, 'newPassword' => 'court'],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testRateLimitsRepeatedAttempts(): void
    {
        $this->createUser('ratelimit@plume.test');
        $client = static::createClient();

        // Le stockage du limiteur survit entre runs locaux : on repart propre.
        $factory = static::getContainer()->get('limiter.account_password');
        \assert($factory instanceof RateLimiterFactory);
        $factory->create('ratelimit@plume.test')->reset();

        $token = $this->tokenFor($client, 'ratelimit@plume.test');

        // 5 essais autorisés (ici tous en 422 : mauvais ancien mot de passe)...
        for ($i = 0; $i < 5; ++$i) {
            $client->request('POST', '/api/v1/account/password', [
                'auth_bearer' => $token,
                'json' => ['currentPassword' => 'wrong', 'newPassword' => 'nouveau-Mdp-456'],
            ]);
            self::assertResponseStatusCodeSame(422);
        }

        // ... le 6e est bloqué par le rate limiter (avant même la vérification).
        $client->request('POST', '/api/v1/account/password', [
            'auth_bearer' => $token,
            'json' => ['currentPassword' => self::PASSWORD, 'newPassword' => 'nouveau-Mdp-456'],
        ]);
        self::assertResponseStatusCodeSame(429);
    }
}
