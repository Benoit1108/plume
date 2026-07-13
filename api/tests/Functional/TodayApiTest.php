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
 * Tests fonctionnels M1.3 : /today (relances dues + à contacter + progression/série),
 * relances (faite / planifier / annuler), /profile, isolation tenant.
 * Events consommés en ligne (transport sync en env test) → journal immédiat.
 */
final class TodayApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE interaction, lead, organization, profile, app_user, refresh_tokens RESTART IDENTITY CASCADE');
    }

    private function createUser(string $email): string
    {
        $container = static::getContainer();
        /** @var EntityManagerInterface $em */
        $em = $container->get(EntityManagerInterface::class);
        /** @var UserPasswordHasherInterface $hasher */
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $tenantId = Uuid::v7();
        $user = new User(Uuid::v7(), $tenantId, $email);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));
        $em->persist($user);
        $em->flush();
        $em->clear();

        return $tenantId->toRfc4122();
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

    /** @param array<string, mixed> $json */
    private function post(Client $client, string $token, string $url, array $json = []): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $client->request('POST', $url, [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/ld+json'],
            'json' => $json,
        ]);
    }

    private function anOrganization(Client $client, string $token, string $name): string
    {
        $response = $this->post($client, $token, '/api/v1/organizations', ['name' => $name, 'type' => 'PUBLISHER']);
        self::assertResponseStatusCodeSame(201);

        /** @var array{id: string} $org */
        $org = $response->toArray();

        return $org['id'];
    }

    private function aLead(Client $client, string $token, string $orgId): string
    {
        $response = $this->post($client, $token, '/api/v1/leads', [
            'organizationId' => $orgId,
            'languagePair' => 'en>fr',
            'source' => 'DIRECT',
            'priority' => 'HIGH',
            'segment' => 'PUBLISHING',
        ]);
        self::assertResponseStatusCodeSame(201);

        /** @var array{id: string} $lead */
        $lead = $response->toArray();

        return $lead['id'];
    }

    /**
     * @return array{followUpsDue: list<array{organizationName: string}>, toContact: list<array{organizationName: string}>, weeklyTarget: int, weeklyDone: int, streak: int}
     */
    private function today(Client $client, string $token): array
    {
        $response = $client->request('GET', '/api/v1/today', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();

        /** @var array{followUpsDue: list<array{organizationName: string}>, toContact: list<array{organizationName: string}>, weeklyTarget: int, weeklyDone: int, streak: int} $data */
        $data = $response->toArray();

        return $data;
    }

    public function testTodaySplitsDueFollowUpsAndLeadsToContact(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Une piste à contacter, une piste contactée (relance auto J+7 : PAS due).
        $this->aLead($client, $token, $this->anOrganization($client, $token, 'Sans contact'));
        $contactedLead = $this->aLead($client, $token, $this->anOrganization($client, $token, 'Contactée'));
        $this->post($client, $token, "/api/v1/leads/$contactedLead/contact");
        self::assertResponseIsSuccessful();

        $today = $this->today($client, $token);
        self::assertCount(1, $today['toContact']);
        self::assertSame('Sans contact', $today['toContact'][0]['organizationName']);
        self::assertCount(0, $today['followUpsDue']);
        self::assertSame(5, $today['weeklyTarget']); // défaut du profil
        self::assertSame(1, $today['weeklyDone']);   // le contact compte

        // Replanifiée à aujourd'hui → elle devient due.
        $response = $this->post($client, $token, "/api/v1/leads/$contactedLead/schedule-follow-up", [
            'dueAt' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
            'label' => 'Relancer ce soir',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame('Relancer ce soir', $response->toArray()['nextFollowUpLabel']);

        $today = $this->today($client, $token);
        self::assertCount(1, $today['followUpsDue']);
        self::assertSame('Contactée', $today['followUpsDue'][0]['organizationName']);
    }

    public function testFollowUpDoneAdvancesCadenceAndCountsAsOutreach(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $leadId = $this->aLead($client, $token, $this->anOrganization($client, $token, 'Actes Sud'));

        $this->post($client, $token, "/api/v1/leads/$leadId/contact");
        $response = $this->post($client, $token, "/api/v1/leads/$leadId/follow-up");
        self::assertResponseIsSuccessful();

        /** @var array{status: string, nextFollowUpAt: string} $lead */
        $lead = $response->toArray();
        self::assertSame('FOLLOWED_UP', $lead['status']);
        // Cadence : après la 1re relance faite, la suivante part à J+21.
        self::assertSame((new \DateTimeImmutable('+21 days'))->format('Y-m-d'), $lead['nextFollowUpAt']);

        self::assertSame(2, $this->today($client, $token)['weeklyDone']); // contact + relance

        // Le journal a tout tracé.
        $response = $client->request('GET', "/api/v1/leads/$leadId/interactions", [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);
        $collection = $response->toArray();
        /** @var list<array{type: string}> $items */
        $items = $collection['member'] ?? $collection['hydra:member'] ?? [];
        self::assertSame(
            ['follow_up_scheduled', 'followed_up', 'follow_up_scheduled', 'contacted', 'created'],
            array_column($items, 'type'),
        );
    }

    public function testCancelFollowUp(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $leadId = $this->aLead($client, $token, $this->anOrganization($client, $token, 'Actes Sud'));
        $this->post($client, $token, "/api/v1/leads/$leadId/contact");

        $client->request('DELETE', "/api/v1/leads/$leadId/follow-up", ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(204);

        $response = $client->request('GET', "/api/v1/leads/$leadId", ['auth_bearer' => $token]);
        // skip_null_values : la clé disparaît quand la relance est annulée.
        self::assertNull($response->toArray()['nextFollowUpAt'] ?? null);
    }

    public function testProfileDefaultsAndUpdateFlowIntoToday(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $response = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();
        self::assertJsonContains(['weeklyGoal' => 5, 'timezone' => 'Europe/Paris']);

        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['weeklyGoal' => 8],
        ]);
        self::assertResponseIsSuccessful();

        $response = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token]);
        self::assertJsonContains(['weeklyGoal' => 8]);
        self::assertSame(8, $this->today($client, $token)['weeklyTarget']);

        // Bornes.
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['weeklyGoal' => 0],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testStreakCountsConsecutiveWeeks(): void
    {
        $tenantId = $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Objectif à 2 pour un test compact.
        $client->request('PATCH', '/api/v1/profile', [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['weeklyGoal' => 2],
        ]);

        // Historique injecté dans le journal : 2 actes il y a 2 semaines, 2 il y a 1 semaine,
        // 2 cette semaine (midi UTC : pas d'effet de bord de fuseau).
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        foreach ([14, 14, 7, 7, 0, 0] as $daysAgo) {
            $connection->executeStatement(
                "INSERT INTO interaction (id, event_id, tenant_id, lead_id, type, payload, occurred_on)
                 VALUES (:id, :event_id, :tenant, 'lead-hist', 'contacted', '{}', :when)",
                [
                    'id' => Uuid::v7()->toRfc4122(),
                    'event_id' => Uuid::v7()->toRfc4122(),
                    'tenant' => $tenantId,
                    'when' => (new \DateTimeImmutable("today -$daysAgo days"))->setTime(12, 0)->format('Y-m-d H:i:s'),
                ],
            );
        }

        $today = $this->today($client, $token);
        self::assertSame(2, $today['weeklyDone']);
        self::assertSame(3, $today['streak']); // 2 semaines passées + la courante (déjà atteinte)
    }

    public function testTodayIsTenantIsolated(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();
        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $tokenB = $this->tokenFor($client, 'b@plume.test');

        $leadId = $this->aLead($client, $tokenA, $this->anOrganization($client, $tokenA, 'Secret A'));
        $this->post($client, $tokenA, "/api/v1/leads/$leadId/contact");

        $todayB = $this->today($client, $tokenB);
        self::assertCount(0, $todayB['toContact']);
        self::assertCount(0, $todayB['followUpsDue']);
        self::assertSame(0, $todayB['weeklyDone']);
    }
}
