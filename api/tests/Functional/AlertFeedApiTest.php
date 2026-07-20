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

/** Gestion des flux d'annonces (Sourcing M3.1b) : CRUD + isolation tenant. */
final class AlertFeedApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';
    private const LD = ['Content-Type' => 'application/ld+json'];

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE alert_feed, app_user, refresh_tokens RESTART IDENTITY CASCADE');
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
        /** @var array{token: string} $data */
        $data = $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => $email, 'password' => self::PASSWORD],
        ])->toArray();

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

    public function testAddListDeactivateAndRemoveFeed(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $client->request('POST', '/api/v1/sources', [
            'auth_bearer' => $token,
            'headers' => self::LD,
            'json' => ['source' => 'RSS', 'url' => 'https://proz.example/rss', 'label' => 'ProZ FR'],
        ]);
        self::assertResponseStatusCodeSame(201);

        $feeds = $this->membersOf($client->request('GET', '/api/v1/sources', ['auth_bearer' => $token])->toArray());
        self::assertCount(1, $feeds);
        self::assertSame('ProZ FR', $feeds[0]['label']);
        self::assertTrue($feeds[0]['active']);
        $id = \is_string($feeds[0]['id']) ? $feeds[0]['id'] : '';

        $client->request('POST', '/api/v1/sources/'.$id.'/deactivate', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(204);
        $feeds = $this->membersOf($client->request('GET', '/api/v1/sources', ['auth_bearer' => $token])->toArray());
        self::assertFalse($feeds[0]['active']);

        $client->request('DELETE', '/api/v1/sources/'.$id, ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(204);
        $feeds = $this->membersOf($client->request('GET', '/api/v1/sources', ['auth_bearer' => $token])->toArray());
        self::assertCount(0, $feeds);
    }

    public function testRejectsInvalidUrl(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $client->request('POST', '/api/v1/sources', [
            'auth_bearer' => $token,
            'headers' => self::LD,
            'json' => ['source' => 'RSS', 'url' => 'pas-une-url'],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testFeedsAreIsolatedPerTenant(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();

        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $client->request('POST', '/api/v1/sources', [
            'auth_bearer' => $tokenA,
            'headers' => self::LD,
            'json' => ['source' => 'RSS', 'url' => 'https://proz.example/rss'],
        ]);

        $tokenB = $this->tokenFor($client, 'b@plume.test');
        $feeds = $this->membersOf($client->request('GET', '/api/v1/sources', ['auth_bearer' => $tokenB])->toArray());
        self::assertCount(0, $feeds);
    }
}
