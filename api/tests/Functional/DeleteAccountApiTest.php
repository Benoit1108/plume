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
 * RGPD — suppression de compte (soft-delete). Exige le mot de passe courant, marque le compte pour
 * suppression, révoque les sessions ; l'accès est coupé immédiatement (login + refresh refusés).
 */
final class DeleteAccountApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens RESTART IDENTITY CASCADE');

        $tokenLimiter = static::getContainer()->get('limiter.token_endpoints');
        \assert($tokenLimiter instanceof RateLimiterFactory);
        $tokenLimiter->create('127.0.0.1')->reset();
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

        /** @var array{token: string} $data */
        $data = $response->toArray();

        return $data['token'];
    }

    public function testDeletesAccountThenLoginIsBlocked(): void
    {
        $this->createUser('del@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'del@plume.test');

        $client->request('DELETE', '/api/v1/account', [
            'auth_bearer' => $token,
            'json' => ['currentPassword' => self::PASSWORD],
        ]);
        self::assertResponseStatusCodeSame(204);

        // Le compte est désactivé immédiatement : même le bon mot de passe ne connecte plus.
        $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => 'del@plume.test', 'password' => self::PASSWORD],
        ]);
        self::assertResponseStatusCodeSame(401);
    }

    public function testRejectsWrongCurrentPassword(): void
    {
        $this->createUser('keep@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'keep@plume.test');

        $client->request('DELETE', '/api/v1/account', [
            'auth_bearer' => $token,
            'json' => ['currentPassword' => 'wrong-password'],
        ]);
        self::assertResponseStatusCodeSame(422);

        // Le compte n'est PAS supprimé : le login fonctionne toujours.
        $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => 'keep@plume.test', 'password' => self::PASSWORD],
        ]);
        self::assertResponseIsSuccessful();
    }

    public function testDeletionRevokesRefreshTokens(): void
    {
        $this->createUser('rev@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'rev@plume.test'); // pose les cookies (jwt + refresh)

        $client->request('DELETE', '/api/v1/account', [
            'auth_bearer' => $token,
            'json' => ['currentPassword' => self::PASSWORD],
        ]);
        self::assertResponseStatusCodeSame(204);

        // Plus aucune session : le refresh token de la session ouverte ne vaut plus.
        $client->request('POST', '/api/v1/token/refresh', ['json' => []]);
        self::assertResponseStatusCodeSame(401);
    }
}
