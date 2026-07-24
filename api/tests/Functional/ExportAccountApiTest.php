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
 * RGPD — export des données du compte : archive ZIP (JSON complet + CSV lisibles), scopée au tenant
 * courant, sans jamais divulguer de secret (tokens OAuth) ni la table d'authentification (app_user).
 */
final class ExportAccountApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    private Connection $connection;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE app_user, organization, connected_mailbox RESTART IDENTITY CASCADE');

        $tokenLimiter = static::getContainer()->get('limiter.token_endpoints');
        \assert($tokenLimiter instanceof RateLimiterFactory);
        $tokenLimiter->create('127.0.0.1')->reset();
    }

    private function createUser(string $email): string
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $tenant = Uuid::v7();
        $user = new User(Uuid::v7(), $tenant, $email);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);
        $em->flush();
        $em->clear();

        return $tenant->toRfc4122();
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

    public function testExportsTenantDataAsZipWithoutSecrets(): void
    {
        $tenant = $this->createUser('exp@plume.test');

        // Une organisation (exportée) + une boîte connectée avec des tokens (jamais exportés).
        $this->connection->executeStatement(
            "INSERT INTO organization (id, tenant_id, name, type, working_languages, segments, do_not_contact, contacts, website, country, notes)
             VALUES (?, ?, 'Éditions Test', 'publisher', '[]', '[]', false, '[]', 'https://test.example', 'FR', 'Une note')",
            [Uuid::v7()->toRfc4122(), $tenant],
        );
        $this->connection->executeStatement(
            "INSERT INTO connected_mailbox (id, tenant_id, provider, email_address, access_token, refresh_token, status, connected_at)
             VALUES (?, ?, 'gmail', 'exp@plume.test', 'super-secret-access', 'super-secret-refresh', 'CONNECTED', '2026-07-24 10:00:00')",
            [Uuid::v7()->toRfc4122(), $tenant],
        );

        $client = static::createClient();
        $token = $this->tokenFor($client, 'exp@plume.test');

        $response = $client->request('GET', '/api/v1/account/export', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('content-type', 'application/zip');

        $entries = $this->unzip($response->getContent());

        self::assertArrayHasKey('export.json', $entries);
        self::assertArrayHasKey('organisations.csv', $entries);
        self::assertArrayHasKey('pistes.csv', $entries);

        $json = $entries['export.json'];
        self::assertStringContainsString('Éditions Test', $json);
        self::assertStringContainsString('exp@plume.test', $json);
        // Aucun secret, aucune table d'authentification.
        self::assertStringNotContainsString('super-secret-access', $json);
        self::assertStringNotContainsString('super-secret-refresh', $json);
        self::assertStringNotContainsString('app_user', $json);

        self::assertStringContainsString('Éditions Test', $entries['organisations.csv']);
    }

    /**
     * @return array<string, string>
     */
    private function unzip(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'export-test-');
        \assert(false !== $path);
        file_put_contents($path, $content);

        $zip = new \ZipArchive();
        $zip->open($path);
        $entries = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $name = (string) $zip->getNameIndex($i);
            $entries[$name] = (string) $zip->getFromIndex($i);
        }
        $zip->close();
        @unlink($path);

        return $entries;
    }
}
