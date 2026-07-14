<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Account\Infrastructure\Persistence\User;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * M2.0 — auth par cookies httpOnly : le login pose les deux cookies, les
 * requêtes passent SANS Authorization, le refresh tourne sur cookie seul,
 * le logout révoque et efface. (En env test, le corps garde aussi les tokens
 * pour les autres fonctionnels — le mécanisme cookie est indépendant.).
 */
final class AuthCookieTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens RESTART IDENTITY CASCADE');

        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $user = new User(Uuid::v7(), Uuid::v7(), 'a@plume.test');
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);
        $em->flush();
        $em->clear();
    }

    public function testLoginSetsHttpOnlyCookiesAndCookieAuthWorks(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => 'a@plume.test', 'password' => self::PASSWORD],
        ]);
        self::assertResponseIsSuccessful();

        // Les deux cookies d'auth sont posés, httpOnly.
        $cookies = $response->getHeaders()['set-cookie'] ?? [];
        $jwtCookie = $this->cookieNamed($cookies, 'plume_jwt');
        $refreshCookie = $this->cookieNamed($cookies, 'refresh_token');
        self::assertNotNull($jwtCookie, 'plume_jwt cookie attendu');
        self::assertNotNull($refreshCookie, 'refresh_token cookie attendu');
        self::assertStringContainsStringIgnoringCase('httponly', $jwtCookie);
        self::assertStringContainsStringIgnoringCase('httponly', $refreshCookie);
        self::assertStringContainsString('/api/v1/token', $refreshCookie); // path restreint

        // Requête authentifiée par COOKIE SEUL (le client BrowserKit les rejoue).
        $me = $client->request('GET', '/api/v1/me')->toArray();
        self::assertSame('a@plume.test', $me['email'] ?? null);
    }

    public function testRefreshRotatesOnCookieAlone(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => 'a@plume.test', 'password' => self::PASSWORD],
        ]);

        // Refresh SANS corps : le refresh token voyage en cookie.
        $response = $client->request('POST', '/api/v1/token/refresh', ['json' => []]);
        self::assertResponseIsSuccessful();
        $cookies = $response->getHeaders()['set-cookie'] ?? [];
        self::assertNotNull($this->cookieNamed($cookies, 'plume_jwt'), 'nouveau JWT en cookie attendu');
        self::assertNotNull($this->cookieNamed($cookies, 'refresh_token'), 'refresh token tourné en cookie attendu');

        // La session reste valable après rotation.
        $client->request('GET', '/api/v1/me');
        self::assertResponseIsSuccessful();
    }

    public function testLogoutRevokesAndClearsCookies(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/login_check', [
            'json' => ['email' => 'a@plume.test', 'password' => self::PASSWORD],
        ]);

        $response = $client->request('POST', '/api/v1/token/invalidate', ['json' => []]);
        self::assertResponseStatusCodeSame(204);
        $cookies = $response->getHeaders()['set-cookie'] ?? [];
        // Les deux cookies sont effacés (expiration passée).
        self::assertNotNull($this->cookieNamed($cookies, 'plume_jwt'));
        self::assertNotNull($this->cookieNamed($cookies, 'refresh_token'));

        // Le refresh token révoqué ne rafraîchit plus rien.
        $client->request('POST', '/api/v1/token/refresh', ['json' => []]);
        self::assertResponseStatusCodeSame(401);
    }

    /** @param string[] $setCookieHeaders */
    private function cookieNamed(array $setCookieHeaders, string $name): ?string
    {
        foreach ($setCookieHeaders as $header) {
            if (str_starts_with($header, $name.'=')) {
                return $header;
            }
        }

        return null;
    }
}
