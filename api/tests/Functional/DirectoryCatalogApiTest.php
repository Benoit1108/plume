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
 * Annuaire suggéré : liste (authentifiée), ajout au Répertoire (crée l'organisation, marquée ensuite
 * « déjà présente »), doublon → 409, entrée inconnue → 404.
 */
final class DirectoryCatalogApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE organization, app_user, refresh_tokens RESTART IDENTITY CASCADE');
    }

    private function tokenFor(Client $client): string
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);
        $user = new User(Uuid::v7(), Uuid::v7(), 'marie@plume.test');
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);
        $em->flush();
        $em->clear();

        /** @var array{token: string} $data */
        $data = $client->request('POST', '/api/v1/login_check', ['json' => ['email' => 'marie@plume.test', 'password' => self::PASSWORD]])->toArray();

        return $data['token'];
    }

    public function testListThenImportThenDuplicate(): void
    {
        $client = static::createClient();
        $token = $this->tokenFor($client);

        // Liste : des entrées, toutes NON encore importées.
        /** @var array{entries: list<array{id: string, alreadyImported: bool}>} $list */
        $list = $client->request('GET', '/api/v1/directory/catalog', ['auth_bearer' => $token])->toArray();
        self::assertNotEmpty($list['entries']);
        self::assertFalse($list['entries'][0]['alreadyImported']);
        $id = $list['entries'][0]['id'];

        // Ajout → 201.
        $client->request('POST', '/api/v1/directory/catalog/import', ['auth_bearer' => $token, 'json' => ['id' => $id]]);
        self::assertResponseStatusCodeSame(201);

        // La même entrée est désormais marquée « déjà présente ».
        /** @var array{entries: list<array{id: string, alreadyImported: bool}>} $after */
        $after = $client->request('GET', '/api/v1/directory/catalog', ['auth_bearer' => $token])->toArray();
        $imported = array_values(array_filter($after['entries'], static fn (array $e): bool => $e['id'] === $id));
        self::assertTrue($imported[0]['alreadyImported']);

        // Doublon → 409.
        $client->request('POST', '/api/v1/directory/catalog/import', ['auth_bearer' => $token, 'json' => ['id' => $id]]);
        self::assertResponseStatusCodeSame(409);

        // Entrée inconnue → 404.
        $client->request('POST', '/api/v1/directory/catalog/import', ['auth_bearer' => $token, 'json' => ['id' => 'nope']]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCatalogRequiresAuthentication(): void
    {
        static::createClient()->request('GET', '/api/v1/directory/catalog');
        self::assertResponseStatusCodeSame(401);
    }
}
