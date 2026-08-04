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

/**
 * Profil : lecture/écriture du nom d'affichage (page Compte) via PATCH merge-patch,
 * et ISOLATION TENANT (chaque tenant ne voit que son profil).
 */
final class ProfileApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE profile, app_user, refresh_tokens RESTART IDENTITY CASCADE');
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

    public function testReadsAndUpdatesDisplayIdentity(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Défaut : pas de nom.
        $before = $client->request('GET', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ])->toArray();
        self::assertNull($before['firstName'] ?? null);

        // PATCH merge-patch : seul le nom change.
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['firstName' => 'Marie', 'lastName' => 'Lefèvre'],
        ]);
        self::assertResponseIsSuccessful();

        $after = $client->request('GET', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ])->toArray();
        self::assertSame('Marie', $after['firstName'] ?? null);
        self::assertSame('Lefèvre', $after['lastName'] ?? null);
    }

    public function testUpdatesDigestFrequencyAndRejectsInvalidValue(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Défaut DAILY.
        $before = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token, 'headers' => ['Accept' => 'application/ld+json']])->toArray();
        self::assertSame('DAILY', $before['digestFrequency'] ?? null);

        // On coupe le digest.
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['digestFrequency' => 'NONE'],
        ]);
        self::assertResponseIsSuccessful();
        $after = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token, 'headers' => ['Accept' => 'application/ld+json']])->toArray();
        self::assertSame('NONE', $after['digestFrequency'] ?? null);

        // Valeur hors enum → 422 (Assert\Choice).
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['digestFrequency' => 'HOURLY'],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdatesDormantThresholdAndRejectsOutOfRange(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Défaut 120 j.
        $before = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token, 'headers' => ['Accept' => 'application/ld+json']])->toArray();
        self::assertSame(120, $before['dormantClientThresholdDays'] ?? null);

        // Réglage à 90 j (persisté).
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['dormantClientThresholdDays' => 90],
        ]);
        self::assertResponseIsSuccessful();
        $after = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token, 'headers' => ['Accept' => 'application/ld+json']])->toArray();
        self::assertSame(90, $after['dormantClientThresholdDays'] ?? null);

        // 0 accepté (désactive la réactivation).
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['dormantClientThresholdDays' => 0],
        ]);
        self::assertResponseIsSuccessful();

        // Hors bornes (> 730) → 422.
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['dormantClientThresholdDays' => 9999],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testTogglesWeeklyReportPreference(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Activé par défaut.
        $before = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token, 'headers' => ['Accept' => 'application/ld+json']])->toArray();
        self::assertTrue($before['weeklyReportEnabled'] ?? null);

        // Opt-out.
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['weeklyReportEnabled' => false],
        ]);
        self::assertResponseIsSuccessful();
        $after = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token, 'headers' => ['Accept' => 'application/ld+json']])->toArray();
        self::assertFalse($after['weeklyReportEnabled'] ?? null);
    }

    public function testAcceptsValidNotificationPreferencesAndRejectsMalformedShape(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Coupure valide (map type → {inApp/email booléens}).
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['notificationPreferences' => ['reply_received' => ['email' => false]]],
        ]);
        self::assertResponseIsSuccessful();

        // Valeur non booléenne → 422 (Assert\All + Collection : défense en profondeur, H-P2d).
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['notificationPreferences' => ['reply_received' => ['email' => 'nope']]],
        ]);
        self::assertResponseStatusCodeSame(422);

        // Champ interne inattendu → 422 (allowExtraFields: false).
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['notificationPreferences' => ['reply_received' => ['bogus' => true]]],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdatesFollowUpCadenceAndRejectsInvalidValue(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Défaut historique.
        $before = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token, 'headers' => ['Accept' => 'application/ld+json']])->toArray();
        self::assertSame([7, 21, 45], $before['followUpCadence'] ?? null);

        // On personnalise la séquence.
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['followUpCadence' => [10, 30]],
        ]);
        self::assertResponseIsSuccessful();
        $after = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token, 'headers' => ['Accept' => 'application/ld+json']])->toArray();
        self::assertSame([10, 30], $after['followUpCadence'] ?? null);

        // Délai hors bornes (> 365) → 422.
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['followUpCadence' => [7, 400]],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdatesPipelineLabels(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Défaut : aucun override.
        $before = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token, 'headers' => ['Accept' => 'application/ld+json']])->toArray();
        self::assertSame([], $before['pipelineLabels'] ?? null);

        // On renomme deux étapes (un libellé vide est ignoré côté domaine).
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['pipelineLabels' => ['WON' => 'Signée', 'SAMPLE_TEST' => 'Essai', 'LOST' => '']],
        ]);
        self::assertResponseIsSuccessful();

        $after = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token, 'headers' => ['Accept' => 'application/ld+json']])->toArray();
        self::assertSame(['WON' => 'Signée', 'SAMPLE_TEST' => 'Essai'], $after['pipelineLabels'] ?? null);
    }

    public function testIdentityIsIsolatedPerTenant(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();

        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $tokenA,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['firstName' => 'Marie'],
        ]);
        self::assertResponseIsSuccessful();

        // B ne voit pas le nom de A.
        $tokenB = $this->tokenFor($client, 'b@plume.test');
        $viewB = $client->request('GET', '/api/v1/profile', [
            'auth_bearer' => $tokenB,
            'headers' => ['Accept' => 'application/ld+json'],
        ])->toArray();
        self::assertNull($viewB['firstName'] ?? null);
    }
}
