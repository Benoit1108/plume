<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use App\Account\Infrastructure\Security\EmailVerificationSigner;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Inscription publique (V2.1b) : crée un compte NON vérifié (login refusé tant que l'email n'est pas
 * confirmé) puis, après vérification, l'auth est autorisée. Email déjà pris → 409 ; entrées invalides
 * → 422 ; jeton de vérification invalide → 422.
 */
final class RegisterApiTest extends ApiTestCase
{
    use MailerAssertionsTrait;

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

    /**
     * Revue SEC-P2a : un mot de passe QUELCONQUE ne doit rien apprendre sur l'existence du compte.
     * Les contrôles d'état (non vérifié, en suppression) étaient évalués AVANT la vérification du
     * mot de passe : `email_not_verified` sur un email inscrit contre « Identifiants invalides » sur
     * un email libre suffisait à énumérer les comptes — alors que le mot de passe oublié répond
     * délibérément la même chose dans tous les cas.
     */
    public function testWrongPasswordRevealsNothingAboutAnUnverifiedAccount(): void
    {
        $this->register('quiet@plume.test');

        $client = static::createClient();
        $response = $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'quiet@plume.test', 'password' => 'totalement-faux']]);
        self::assertSame(401, $response->getStatusCode());
        $existing = $response->getContent(false);

        $response = $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'jamais-inscrit@plume.test', 'password' => 'totalement-faux']]);
        self::assertSame(401, $response->getStatusCode());
        $unknown = $response->getContent(false);

        self::assertStringNotContainsString('email_not_verified', $existing);
        self::assertSame($unknown, $existing, 'un compte inscrit et un email libre doivent répondre à l\'identique');

        // En revanche, avec le BON mot de passe, le code stable revient : le front propose le renvoi.
        $response = $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'quiet@plume.test', 'password' => 'secret-Test-123']]);
        self::assertSame(401, $response->getStatusCode());
        self::assertStringContainsString('email_not_verified', $response->getContent(false));
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

    /** Revue « politique de mot de passe » : le refus doit NOMMER les règles non tenues. */
    public function testRejectsAWeakPasswordAndNamesTheUnmetRules(): void
    {
        $client = static::createClient();
        $response = $client->request('POST', '/api/v1/register', [
            'json' => ['email' => 'weak@plume.test', 'password' => 'bonjour123', 'acceptTerms' => true],
        ]);

        self::assertSame(422, $response->getStatusCode());
        /** @var array{detail: string, rules: list<string>} $body */
        $body = $response->toArray(false);
        self::assertSame('invalid_password', $body['detail']);
        self::assertSame(['missing_uppercase', 'missing_special'], $body['rules']);
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

    public function testVerificationEmailCarriesAWorkingToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/v1/register', ['json' => ['email' => 'chain@plume.test', 'password' => 'secret-Test-123', 'acceptTerms' => true]]);
        self::assertResponseStatusCodeSame(201);

        // La chaîne complète est vérifiée : un email EST parti, et le jeton QU'IL CONTIENT active bien
        // le compte (un contrôleur qui n'enverrait rien, ou le mauvais jeton, ferait échouer ce test).
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(\Symfony\Component\Mime\Email::class, $message);
        $body = $message->getTextBody();
        \assert(\is_string($body));
        self::assertSame(1, preg_match('#/verify-email\?token=([^\s]+)#', $body, $m));
        $token = urldecode($m[1] ?? '');

        $client->request('POST', '/api/v1/account/verify-email', ['json' => ['token' => $token]]);
        self::assertResponseStatusCodeSame(204);
        $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'chain@plume.test', 'password' => 'secret-Test-123']]);
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
