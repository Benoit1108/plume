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
 * 2FA TOTP : enrôlement en deux temps (code valide exigé pour activer), login qui exige l'OTP
 * (`2fa_required` → `2fa_invalid` → succès), ANTI-REJEU (un même code ne sert qu'une fois),
 * code de secours à usage unique, désactivation par mot de passe. + Sessions : liste avec session
 * courante, révocation des autres.
 */
final class TwoFactorApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    private Connection $connection;
    private TotpService $totp;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens RESTART IDENTITY CASCADE');

        $totp = static::getContainer()->get(TotpService::class);
        \assert($totp instanceof TotpService);
        $this->totp = $totp;

        $tokenLimiter = static::getContainer()->get('limiter.token_endpoints');
        \assert($tokenLimiter instanceof RateLimiterFactory);
        $tokenLimiter->create('127.0.0.1')->reset();

        // Le LOGIN THROTTLING (5 échecs/15 min) survit aussi entre runs locaux — or ce test fait
        // des échecs de login DÉLIBÉRÉS (OTP manquant/faux/rejoué) : on repart d'un pool propre.
        $pool = static::getContainer()->get('cache.rate_limiter');
        \assert($pool instanceof \Symfony\Component\Cache\Adapter\AdapterInterface);
        $pool->clear();
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

    /** @param array<string, string> $extra */
    private function tokenFor(Client $client, string $email, array $extra = []): string
    {
        $response = $client->request('POST', '/api/v1/login_check', [
            'json' => array_merge(['email' => $email, 'password' => self::PASSWORD], $extra),
        ]);

        /** @var array{token: string} $data */
        $data = $response->toArray();

        return $data['token'];
    }

    public function testFullTotpLifecycle(): void
    {
        $this->createUser('totp@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'totp@plume.test');

        // Setup : secret candidat + URI de provisionnement. Un mauvais code ne confirme PAS.
        $response = $client->request('POST', '/api/v1/account/2fa/setup', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseIsSuccessful();
        /** @var array{secret: string, otpauthUri: string} $setup */
        $setup = $response->toArray();
        self::assertStringContainsString('otpauth://totp/', $setup['otpauthUri']);

        // Chiffré AU REPOS (ADR-0027) : la colonne ne contient PAS le secret en clair.
        $storedPending = $this->connection->fetchOne("SELECT totp_pending_secret FROM app_user WHERE email = 'totp@plume.test'");
        self::assertIsString($storedPending);
        self::assertNotSame($setup['secret'], $storedPending);
        self::assertStringNotContainsString($setup['secret'], $storedPending);

        $client->request('POST', '/api/v1/account/2fa/confirm', ['auth_bearer' => $token, 'json' => ['code' => '000000']]);
        self::assertResponseStatusCodeSame(422);

        // Confirm avec un vrai code → codes de secours en clair (une fois).
        $response = $client->request('POST', '/api/v1/account/2fa/confirm', [
            'auth_bearer' => $token, 'json' => ['code' => $this->totp->currentCode($setup['secret'])],
        ]);
        self::assertResponseIsSuccessful();
        /** @var array{backupCodes: list<string>} $confirmed */
        $confirmed = $response->toArray();
        self::assertCount(8, $confirmed['backupCodes']);

        // Login sans OTP → 401 `2fa_required` ; mauvais OTP → `2fa_invalid` ; bon OTP → succès.
        $response = $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'totp@plume.test', 'password' => self::PASSWORD]]);
        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('2fa_required', (string) $response->getContent(false));

        $response = $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'totp@plume.test', 'password' => self::PASSWORD, 'otp' => '123456']]);
        self::assertResponseStatusCodeSame(401);
        self::assertStringContainsString('2fa_invalid', (string) $response->getContent(false));

        $code = $this->totp->currentCode($setup['secret']);
        $this->tokenFor($client, 'totp@plume.test', ['otp' => $code]);

        // ANTI-REJEU : le même code, immédiatement rejoué, est refusé.
        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'totp@plume.test', 'password' => self::PASSWORD, 'otp' => $code]]);
        self::assertResponseStatusCodeSame(401);

        // Code de secours : accepté UNE fois, consommé ensuite.
        $backup = $confirmed['backupCodes'][0];
        $this->tokenFor($client, 'totp@plume.test', ['otp' => $backup]);
        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'totp@plume.test', 'password' => self::PASSWORD, 'otp' => $backup]]);
        self::assertResponseStatusCodeSame(401);

        // Désactivation (mot de passe exigé) → le login redevient mot-de-passe-seul.
        $freshCode = $this->totp->currentCode($setup['secret'], time() + 30); // pas suivant (anti-rejeu)
        $token = $this->tokenFor($client, 'totp@plume.test', ['otp' => $freshCode]);
        $client->request('POST', '/api/v1/account/2fa/disable', ['auth_bearer' => $token, 'json' => ['currentPassword' => 'wrong']]);
        self::assertResponseStatusCodeSame(422);
        $client->request('POST', '/api/v1/account/2fa/disable', ['auth_bearer' => $token, 'json' => ['currentPassword' => self::PASSWORD]]);
        self::assertResponseStatusCodeSame(204);

        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'totp@plume.test', 'password' => self::PASSWORD]]);
        self::assertResponseIsSuccessful();
    }

    public function testEnablingTwoFactorRevokesExistingSessions(): void
    {
        $this->createUser('revoke2fa@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'revoke2fa@plume.test'); // pose un refresh token (session)
        self::assertSame(1, $this->refreshCount('revoke2fa@plume.test'));

        $secret = $client->request('POST', '/api/v1/account/2fa/setup', ['auth_bearer' => $token, 'json' => []])->toArray()['secret'];
        \assert(\is_string($secret));
        $client->request('POST', '/api/v1/account/2fa/confirm', ['auth_bearer' => $token, 'json' => ['code' => $this->totp->currentCode($secret)]]);
        self::assertResponseIsSuccessful();

        // Activer la 2FA ferme les sessions déjà ouvertes.
        self::assertSame(0, $this->refreshCount('revoke2fa@plume.test'));
    }

    private function refreshCount(string $username): int
    {
        $value = $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens WHERE username = ?', [$username]);

        return is_numeric($value) ? (int) $value : -1;
    }

    public function testSessionsListAndRevokeOthers(): void
    {
        $this->createUser('sess@plume.test');

        // Deux sessions : A (cookies conservés par ce client) puis B.
        $clientA = static::createClient();
        $tokenA = $this->tokenFor($clientA, 'sess@plume.test');
        $clientB = static::createClient();
        $this->tokenFor($clientB, 'sess@plume.test');

        // NB : deux clients coexistent → on inspecte CHAQUE réponse directement (les assertions
        // statiques assertResponse* regardent le DERNIER client créé, pas forcément le bon).
        $response = $clientA->request('GET', '/api/v1/token/sessions', ['auth_bearer' => $tokenA]);
        self::assertSame(200, $response->getStatusCode());
        /** @var array{sessions: list<array{id: int, current: bool}>} $data */
        $data = $response->toArray();
        self::assertCount(2, $data['sessions']);
        self::assertCount(1, array_filter($data['sessions'], static fn (array $s): bool => $s['current']));

        // Révoque les autres : il ne reste que la session courante (celle du cookie de A).
        $response = $clientA->request('POST', '/api/v1/token/sessions/revoke-others', ['auth_bearer' => $tokenA, 'json' => []]);
        self::assertSame(204, $response->getStatusCode());
        $remaining = $this->connection->fetchOne('SELECT COUNT(*) FROM refresh_tokens WHERE username = ?', ['sess@plume.test']);
        self::assertSame(1, is_numeric($remaining) ? (int) $remaining : -1);

        // La session B ne peut plus se rafraîchir ; la A si.
        $response = $clientB->request('POST', '/api/v1/token/refresh', ['json' => []]);
        self::assertSame(401, $response->getStatusCode());
        $response = $clientA->request('POST', '/api/v1/token/refresh', ['json' => []]);
        self::assertSame(200, $response->getStatusCode());
    }
}
