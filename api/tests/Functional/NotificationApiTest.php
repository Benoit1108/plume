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
 * Centre de notifications : liste scopée au tenant (les plus récentes d'abord), marquage lu
 * unitaire et global — idempotents et STRICTEMENT bornés au tenant courant (une notification d'un
 * autre tenant n'est ni visible ni marquable).
 */
final class NotificationApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    private Connection $connection;
    private string $tenantA;

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $this->connection = $connection;
        $this->connection->executeStatement('TRUNCATE TABLE app_user, refresh_tokens, notification RESTART IDENTITY CASCADE');

        $tokenLimiter = static::getContainer()->get('limiter.token_endpoints');
        \assert($tokenLimiter instanceof RateLimiterFactory);
        $tokenLimiter->create('127.0.0.1')->reset();

        $this->tenantA = $this->createUser('notif-a@plume.test');
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

    private function seedNotification(string $tenantId, string $type, string $occurredOn): string
    {
        $id = Uuid::v7()->toRfc4122();
        $this->connection->executeStatement(
            "INSERT INTO notification (id, event_id, tenant_id, type, payload, occurred_on)
             VALUES (?, ?, ?, ?, '{\"leadId\": \"L1\"}', ?)",
            [$id, 'evt-'.$id, $tenantId, $type, $occurredOn],
        );

        return $id;
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

    public function testListsOwnNotificationsMostRecentFirst(): void
    {
        $tenantB = Uuid::v7()->toRfc4122();
        $old = $this->seedNotification($this->tenantA, 'reply_received', '2026-07-27 10:00:00');
        $recent = $this->seedNotification($this->tenantA, 'followup_due', '2026-07-28 09:00:00');
        $this->seedNotification($tenantB, 'reply_received', '2026-07-28 11:00:00'); // autre tenant

        $client = static::createClient();
        $token = $this->tokenFor($client, 'notif-a@plume.test');

        $response = $client->request('GET', '/api/v1/notifications', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();
        /** @var array{member?: list<array{id: string, type: string, readAt?: ?string}>} $data */
        $data = $response->toArray();
        $items = $data['member'] ?? [];

        self::assertCount(2, $items); // jamais celle du tenant B
        self::assertSame($recent, $items[0]['id']);
        self::assertSame($old, $items[1]['id']);
        self::assertNull($items[0]['readAt'] ?? null); // null omis de la sérialisation = non lue
    }

    public function testInAppMutedTypesAreHiddenFromTheFeed(): void
    {
        // Préférence : la traductrice coupe le canal in-app des « candidats à trier » (email gardé).
        $this->connection->executeStatement(
            "INSERT INTO profile (tenant_id, weekly_goal, timezone, notification_preferences)
             VALUES (?, 5, 'Europe/Paris', '{\"candidate_to_triage\": {\"inApp\": false, \"email\": true}}')",
            [$this->tenantA],
        );
        $shown = $this->seedNotification($this->tenantA, 'reply_received', '2026-07-28 09:00:00');
        $this->seedNotification($this->tenantA, 'candidate_to_triage', '2026-07-28 10:00:00'); // coupé in-app

        $client = static::createClient();
        $token = $this->tokenFor($client, 'notif-a@plume.test');

        $response = $client->request('GET', '/api/v1/notifications', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();
        /** @var array{member?: list<array{id: string, type: string}>} $data */
        $data = $response->toArray();
        $items = $data['member'] ?? [];

        self::assertCount(1, $items); // le candidat à trier est masqué de la cloche
        self::assertSame($shown, $items[0]['id']);
        self::assertSame('reply_received', $items[0]['type']);
    }

    public function testMarkReadIsTenantScopedAndIdempotent(): void
    {
        $mine = $this->seedNotification($this->tenantA, 'reply_received', '2026-07-28 09:00:00');
        $tenantB = Uuid::v7()->toRfc4122();
        $foreign = $this->seedNotification($tenantB, 'reply_received', '2026-07-28 09:00:00');

        $client = static::createClient();
        $token = $this->tokenFor($client, 'notif-a@plume.test');

        $client->request('POST', '/api/v1/notifications/'.$mine.'/read', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseStatusCodeSame(204);
        self::assertNotNull($this->connection->fetchOne('SELECT read_at FROM notification WHERE id = ?', [$mine]));

        // Une notification d'un AUTRE tenant : 204 (rien à divulguer) mais RIEN n'est marqué.
        $client->request('POST', '/api/v1/notifications/'.$foreign.'/read', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseStatusCodeSame(204);
        self::assertNull($this->connection->fetchOne('SELECT read_at FROM notification WHERE id = ?', [$foreign]));
    }

    public function testMarkAllReadOnlyTouchesOwnTenant(): void
    {
        $this->seedNotification($this->tenantA, 'reply_received', '2026-07-28 09:00:00');
        $this->seedNotification($this->tenantA, 'followup_due', '2026-07-28 10:00:00');
        $tenantB = Uuid::v7()->toRfc4122();
        $foreign = $this->seedNotification($tenantB, 'reply_received', '2026-07-28 09:00:00');

        $client = static::createClient();
        $token = $this->tokenFor($client, 'notif-a@plume.test');

        $client->request('POST', '/api/v1/notifications/read-all', ['auth_bearer' => $token, 'json' => []]);
        self::assertResponseStatusCodeSame(204);

        $unreadMine = $this->connection->fetchOne('SELECT COUNT(*) FROM notification WHERE tenant_id = ? AND read_at IS NULL', [$this->tenantA]);
        self::assertSame(0, is_numeric($unreadMine) ? (int) $unreadMine : -1);
        self::assertNull($this->connection->fetchOne('SELECT read_at FROM notification WHERE id = ?', [$foreign]));
    }
}
