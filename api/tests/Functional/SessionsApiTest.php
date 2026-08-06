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
 * Lot « densité » : une session doit être RECONNAISSABLE (appareil + dernière activité) et le
 * nombre de sessions doit rester tenable — sans quoi le conseil « révoquez ce que vous ne
 * reconnaissez pas » de la page Compte est inapplicable.
 *
 * (Le reste du cycle sessions — session courante, révocation des autres — est couvert par
 * TwoFactorApiTest::testSessionsListAndRevokeOthers.)
 */
final class SessionsApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';
    private const FIREFOX_LINUX = 'Mozilla/5.0 (X11; Linux x86_64; rv:128.0) Gecko/20100101 Firefox/128.0';

    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens RESTART IDENTITY CASCADE');

        $tokenLimiter = static::getContainer()->get('limiter.token_endpoints');
        \assert($tokenLimiter instanceof RateLimiterFactory);
        $tokenLimiter->create('127.0.0.1')->reset();
    }

    public function testSessionCarriesItsDeviceAndLastActivity(): void
    {
        $this->createUser('device@plume.test');

        $client = static::createClient();
        $token = $this->login($client, 'device@plume.test', self::FIREFOX_LINUX);

        $response = $client->request('GET', '/api/v1/token/sessions', ['auth_bearer' => $token]);
        self::assertSame(200, $response->getStatusCode());

        /** @var array{sessions: list<array{browser: ?string, platform: ?string, lastSeenAt: ?string, current: bool}>} $data */
        $data = $response->toArray();
        self::assertCount(1, $data['sessions']);

        $session = $data['sessions'][0];
        self::assertSame('Firefox', $session['browser']);
        self::assertSame('Linux', $session['platform']);
        self::assertNotNull($session['lastSeenAt']);
        self::assertTrue($session['current']);
    }

    /** Une session ouverte avant la migration (ni agent ni activité) reste listable, sans planter. */
    public function testLegacySessionWithoutDeviceStaysListable(): void
    {
        $this->createUser('legacy@plume.test');
        $this->insertSession('legacy@plume.test', 'legacy-token', valid: '+10 days');

        $client = static::createClient();
        $token = $this->login($client, 'legacy@plume.test', self::FIREFOX_LINUX);

        /** @var array{sessions: list<array{browser: ?string, platform: ?string, lastSeenAt: ?string}>} $data */
        $data = $client->request('GET', '/api/v1/token/sessions', ['auth_bearer' => $token])->toArray();
        self::assertCount(2, $data['sessions']);

        $legacy = $data['sessions'][1]; // les plus récentes d'abord : l'ancienne ferme la liste
        self::assertNull($legacy['browser']);
        self::assertNull($legacy['platform']);
        self::assertNull($legacy['lastSeenAt']);
    }

    public function testLoginClosesExpiredSessionsAndCapsTheLiveOnes(): void
    {
        $this->createUser('prune@plume.test');

        $this->insertSession('prune@plume.test', 'expired-token', valid: '-1 day');
        for ($i = 0; $i < 12; ++$i) {
            $this->insertSession('prune@plume.test', 'live-token-'.$i, valid: '+10 days');
        }
        // Une session d'un AUTRE compte : jamais touchée par la purge d'un voisin.
        $this->createUser('bystander@plume.test');
        $this->insertSession('bystander@plume.test', 'bystander-token', valid: '-1 day');

        self::assertSame(13, $this->sessionCount('prune@plume.test'));

        $client = static::createClient();
        $this->login($client, 'prune@plume.test', self::FIREFOX_LINUX);

        // Plafond à 10 : la session tout juste ouverte + les 9 plus récentes ; l'expirée est fermée.
        self::assertSame(10, $this->sessionCount('prune@plume.test'));
        self::assertSame(0, $this->countToken('expired-token'));
        self::assertSame(0, $this->countToken('live-token-0')); // la plus ancienne des vivantes
        self::assertSame(1, $this->countToken('live-token-11')); // la plus récente est conservée
        self::assertSame(1, $this->countToken('bystander-token'));
    }

    private function login(Client $client, string $email, string $userAgent): string
    {
        $response = $client->request('POST', '/api/v1/login_check', [
            'headers' => ['User-Agent' => $userAgent],
            'json' => ['email' => $email, 'password' => self::PASSWORD],
        ]);

        /** @var array{token: string} $data */
        $data = $response->toArray();

        return $data['token'];
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

    private function insertSession(string $username, string $token, string $valid): void
    {
        $this->connection->executeStatement(
            'INSERT INTO refresh_tokens (refresh_token, username, valid) VALUES (?, ?, ?)',
            [$token, $username, (new \DateTimeImmutable($valid))->format('Y-m-d H:i:s')],
        );
    }

    private function sessionCount(string $username): int
    {
        $value = $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens WHERE username = ?', [$username]);

        return is_numeric($value) ? (int) $value : -1;
    }

    private function countToken(string $token): int
    {
        $value = $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens WHERE refresh_token = ?', [$token]);

        return is_numeric($value) ? (int) $value : -1;
    }
}
