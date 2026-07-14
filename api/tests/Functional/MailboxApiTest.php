<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Account\Infrastructure\Persistence\User;
use App\Mailbox\Infrastructure\OAuth\FakeMailboxConnector;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Tests fonctionnels M2.1 : flux OAuth complet sur le connecteur factice,
 * state anti-CSRF lié au tenant, tokens jamais exposés, révocation, isolation.
 */
final class MailboxApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE connected_mailbox, app_user, refresh_tokens RESTART IDENTITY CASCADE');
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

    /** @return string le state extrait de l'URL d'autorisation */
    private function startOAuth(Client $client, string $token, string $provider = 'GMAIL'): string
    {
        $response = $client->request('POST', '/api/v1/mailbox/oauth/start', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['provider' => $provider],
        ]);
        self::assertResponseIsSuccessful();
        $url = $response->toArray()['authorizationUrl'] ?? null;
        self::assertIsString($url);

        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        self::assertIsString($state);

        return $state;
    }

    /** @return array<string, mixed> */
    private function connect(Client $client, string $token, string $state, string $code = FakeMailboxConnector::ACCEPTED_CODE): array
    {
        $response = $client->request('POST', '/api/v1/mailbox/connect', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['code' => $code, 'state' => $state],
        ]);

        /** @var array<string, mixed> $data */
        $data = $response->toArray(false);

        return $data;
    }

    public function testFullOAuthJourneyOnFakeConnector(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Pas de boîte : 200 avec statut NONE (singleton — jamais de 404 bruyant).
        $none = $client->request('GET', '/api/v1/mailbox', ['auth_bearer' => $token])->toArray();
        self::assertSame('NONE', $none['status']);

        // start → state → connect (le connecteur factice accepte le code).
        $state = $this->startOAuth($client, $token);
        $mailbox = $this->connect($client, $token, $state);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('GMAIL', $mailbox['provider']);
        self::assertSame('CONNECTED', $mailbox['status']);
        self::assertSame('traductrice@gmail.example', $mailbox['emailAddress']);
        // JAMAIS un token dans une réponse.
        self::assertStringNotContainsString('fake-access-token', json_encode($mailbox, \JSON_THROW_ON_ERROR));

        // GET reflète l'état ; les tokens sont chiffrés en base (pas de clair).
        $view = $client->request('GET', '/api/v1/mailbox', ['auth_bearer' => $token])->toArray();
        self::assertSame('CONNECTED', $view['status']);
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $stored = $connection->fetchOne('SELECT access_token FROM connected_mailbox LIMIT 1');
        self::assertIsString($stored);
        self::assertNotSame('', $stored);
        self::assertStringNotContainsString('fake-access-token', $stored);

        // Révocation : 204, tokens effacés, statut REVOKED.
        $client->request('DELETE', '/api/v1/mailbox', ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(204);
        $after = $client->request('GET', '/api/v1/mailbox', ['auth_bearer' => $token])->toArray();
        self::assertSame('REVOKED', $after['status']);
        self::assertNull($connection->fetchOne('SELECT access_token FROM connected_mailbox LIMIT 1'));
    }

    public function testConnectOutlookViaStateProvider(): void
    {
        // D1 : Outlook aussi. Sans identifiants Microsoft, le connecteur factice
        // prend le relais — mais la boîte enregistre bien le provider OUTLOOK.
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $state = $this->startOAuth($client, $token, 'OUTLOOK');
        $mailbox = $this->connect($client, $token, $state);
        self::assertResponseStatusCodeSame(201);
        self::assertSame('OUTLOOK', $mailbox['provider']);
        self::assertSame('CONNECTED', $mailbox['status']);
    }

    public function testUnknownProviderAtStartIsRejected(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $client->request('POST', '/api/v1/mailbox/oauth/start', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => ['provider' => 'PIGEON'],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testStateFromAnotherTenantIsRejected(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();
        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $tokenB = $this->tokenFor($client, 'b@plume.test');

        // Anti-CSRF : le state émis pour A ne connecte jamais la boîte de B.
        $stateA = $this->startOAuth($client, $tokenA);
        $this->connect($client, $tokenB, $stateA);
        self::assertResponseStatusCodeSame(422);
    }

    public function testInvalidCodeIsACleanFailure(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $state = $this->startOAuth($client, $token);

        $this->connect($client, $token, $state, 'mauvais-code');
        // MailboxConnectionFailed → 500 générique ? Non : erreur propre attendue.
        self::assertResponseStatusCodeSame(422);
    }

    public function testMailboxIsTenantIsolated(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();
        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $tokenB = $this->tokenFor($client, 'b@plume.test');

        $state = $this->startOAuth($client, $tokenA);
        $this->connect($client, $tokenA, $state);

        // B ne voit pas la boîte de A (statut NONE, aucune fuite d'adresse).
        $viewB = $client->request('GET', '/api/v1/mailbox', ['auth_bearer' => $tokenB])->toArray();
        self::assertSame('NONE', $viewB['status']);
        self::assertSame('', $viewB['emailAddress']);
        // B ne peut pas la révoquer non plus.
        $client->request('DELETE', '/api/v1/mailbox', ['auth_bearer' => $tokenB]);
        self::assertResponseStatusCodeSame(404);
    }
}
