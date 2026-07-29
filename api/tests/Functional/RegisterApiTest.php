<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Account\Infrastructure\Security\EmailVerificationSigner;
use Doctrine\DBAL\Connection;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Inscription publique (V2.1b) : crée un compte NON vérifié (login refusé tant que l'email n'est pas
 * confirmé) puis, après vérification, l'auth est autorisée. Email déjà pris → 409 ; entrées invalides
 * → 422 ; jeton de vérification invalide → 422.
 */
final class RegisterApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens RESTART IDENTITY CASCADE');

        foreach (['limiter.registration', 'limiter.token_endpoints'] as $id) {
            $factory = static::getContainer()->get($id);
            \assert($factory instanceof RateLimiterFactory);
            $factory->create('127.0.0.1')->reset();
        }
    }

    private function register(string $email, string $password = 'secret-Test-123', bool $acceptTerms = true): void
    {
        static::createClient()->request('POST', '/api/v1/register', [
            'json' => ['email' => $email, 'password' => $password, 'acceptTerms' => $acceptTerms],
        ]);
    }

    public function testRegisterCreatesUnverifiedAccountThenVerificationUnlocksLogin(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/register', [
            'json' => ['email' => 'new@plume.test', 'password' => 'secret-Test-123', 'acceptTerms' => true],
        ]);
        self::assertResponseStatusCodeSame(201);

        // Tant que l'email n'est pas vérifié, le login est refusé.
        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'new@plume.test', 'password' => 'secret-Test-123']]);
        self::assertResponseStatusCodeSame(401);

        // On confirme l'email (jeton produit par le même signer que le contrôleur).
        $signer = static::getContainer()->get(EmailVerificationSigner::class);
        \assert($signer instanceof EmailVerificationSigner);
        $client->request('POST', '/api/v1/account/verify-email', ['json' => ['token' => $signer->sign('new@plume.test')]]);
        self::assertResponseStatusCodeSame(204);

        // Désormais le login fonctionne.
        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'new@plume.test', 'password' => 'secret-Test-123']]);
        self::assertResponseIsSuccessful();
    }

    public function testDuplicateEmailIsRejected(): void
    {
        $this->register('dup@plume.test');
        $client = static::createClient();
        $client->request('POST', '/api/v1/register', [
            'json' => ['email' => 'dup@plume.test', 'password' => 'secret-Test-123', 'acceptTerms' => true],
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testRejectsInvalidInput(): void
    {
        $client = static::createClient();

        $client->request('POST', '/api/v1/register', ['json' => ['email' => 'not-an-email', 'password' => 'secret-Test-123', 'acceptTerms' => true]]);
        self::assertResponseStatusCodeSame(422);

        $client->request('POST', '/api/v1/register', ['json' => ['email' => 'a@plume.test', 'password' => 'court', 'acceptTerms' => true]]);
        self::assertResponseStatusCodeSame(422);

        $client->request('POST', '/api/v1/register', ['json' => ['email' => 'b@plume.test', 'password' => 'secret-Test-123', 'acceptTerms' => false]]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testVerifyWithInvalidTokenIsRejected(): void
    {
        static::createClient()->request('POST', '/api/v1/account/verify-email', ['json' => ['token' => 'garbage.token']]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testEmailIsNormalisedAndLoginIsCaseInsensitive(): void
    {
        $client = static::createClient();
        // Inscription avec des majuscules — stockée en minuscules.
        $client->request('POST', '/api/v1/register', ['json' => ['email' => 'Jane.Doe@Example.COM', 'password' => 'secret-Test-123', 'acceptTerms' => true]]);
        self::assertResponseStatusCodeSame(201);

        $signer = static::getContainer()->get(EmailVerificationSigner::class);
        \assert($signer instanceof EmailVerificationSigner);
        $client->request('POST', '/api/v1/account/verify-email', ['json' => ['token' => $signer->sign('jane.doe@example.com')]]);
        self::assertResponseStatusCodeSame(204);

        // Login avec ENCORE une autre casse → doit fonctionner (provider insensible à la casse).
        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'JANE.DOE@example.com', 'password' => 'secret-Test-123']]);
        self::assertResponseIsSuccessful();
    }

    public function testResendVerificationAlwaysReturns204(): void
    {
        $this->register('resend@plume.test');
        $client = static::createClient();

        $client->request('POST', '/api/v1/account/verify-email/resend', ['json' => ['email' => 'resend@plume.test']]);
        self::assertResponseStatusCodeSame(204);
        // Anti-énumération : même 204 pour un email inconnu.
        $client->request('POST', '/api/v1/account/verify-email/resend', ['json' => ['email' => 'nobody@plume.test']]);
        self::assertResponseStatusCodeSame(204);
    }
}
