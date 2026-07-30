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
 * Tests fonctionnels M1.5 : /dashboard — taux par piste calculés sur le journal
 * (transport sync en test), pipeline, activité hebdo, segments, isolation tenant.
 */
final class DashboardApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE draft, template, interaction, lead, organization, profile, app_user, refresh_tokens RESTART IDENTITY CASCADE');
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

    private function aLead(Client $client, string $token, string $name, string $segment = 'PUBLISHING'): string
    {
        $org = $this->post($client, $token, '/api/v1/organizations', ['name' => $name, 'type' => 'PUBLISHER'])->toArray();
        $lead = $this->post($client, $token, '/api/v1/leads', [
            'organizationId' => $org['id'],
            'languagePair' => 'en>fr',
            'source' => 'DIRECT',
            'priority' => 'HIGH',
            'segment' => $segment,
        ])->toArray();
        self::assertIsString($lead['id'] ?? null);

        return $lead['id'];
    }

    /**
     * @return array{contacted: int, replied: int, won: int, lost: int, activeLeads: int, outreachThisMonth: int, weeklyTarget: int, firstResponseDelayDays: float|null, pipelineValue: int, wonValue: int, pipeline: list<array{status: string, count: int}>, weeklyActivity: list<array{weekStart: string, acts: int}>, segments: list<array{segment: string, contacted: int, replied: int, won: int}>}
     */
    private function dashboard(Client $client, string $token): array
    {
        $response = $client->request('GET', '/api/v1/dashboard', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();

        /** @var array{contacted: int, replied: int, won: int, lost: int, activeLeads: int, outreachThisMonth: int, weeklyTarget: int, firstResponseDelayDays: float|null, pipelineValue: int, wonValue: int, pipeline: list<array{status: string, count: int}>, weeklyActivity: list<array{weekStart: string, acts: int}>, segments: list<array{segment: string, contacted: int, replied: int, won: int}>} $data */
        $data = $response->toArray();

        return $data;
    }

    public function testEmptyDashboardHasDefaults(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $dashboard = $this->dashboard($client, $token);

        self::assertSame(0, $dashboard['contacted']);
        self::assertSame(0, $dashboard['activeLeads']);
        self::assertSame(5, $dashboard['weeklyTarget']);
        self::assertSame([], $dashboard['pipeline']);
        self::assertSame([], $dashboard['segments']);
        self::assertCount(8, $dashboard['weeklyActivity']);
        $acts = array_column($dashboard['weeklyActivity'], 'acts');
        self::assertSame([0, 0, 0, 0, 0, 0, 0, 0], $acts);
        self::assertNull($dashboard['firstResponseDelayDays'] ?? null); // aucune réponse encore (API omet le null)
    }

    public function testFirstResponseDelayIsAveragedFromTheJournal(): void
    {
        $tenant = $this->createUser('a@plume.test');
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        // Contact le 1er, réponse le 4 → délai de 3 jours (lu sur les horodatages du journal).
        $connection->executeStatement(
            "INSERT INTO interaction (id, event_id, tenant_id, lead_id, type, payload, occurred_on) VALUES
             (?, 'evt-c', ?, 'L1', 'contacted', '{}', '2026-07-01 10:00:00'),
             (?, 'evt-r', ?, 'L1', 'reply', '{}', '2026-07-04 10:00:00')",
            [Uuid::v7()->toRfc4122(), $tenant, Uuid::v7()->toRfc4122(), $tenant],
        );

        $client = static::createClient();
        $dashboard = $this->dashboard($client, $this->tokenFor($client, 'a@plume.test'));

        self::assertSame(3.0, (float) $dashboard['firstResponseDelayDays']); // 3 jours (JSON peut rendre 3 ou 3.0)
    }

    public function testEstimatedValuesFeedPipelineAndWonTotals(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $active = $this->aLead($client, $token, 'Active Org');
        $won = $this->aLead($client, $token, 'Won Org');

        foreach ([[$active, 2000], [$won, 5000]] as [$leadId, $value]) {
            $client->request('PATCH', '/api/v1/leads/'.$leadId.'/estimated-value', [
                'auth_bearer' => $token, 'headers' => ['Content-Type' => 'application/json'], 'json' => ['estimatedValue' => $value],
            ]);
        }
        // Gagne la 2e piste (contact → réponse → gagnée) : elle sort du pipeline, entre en « gagné ».
        $this->post($client, $token, '/api/v1/leads/'.$won.'/contact');
        $this->post($client, $token, '/api/v1/leads/'.$won.'/reply');
        $this->post($client, $token, '/api/v1/leads/'.$won.'/win');

        $dashboard = $this->dashboard($client, $token);
        self::assertSame(2000, $dashboard['pipelineValue']);
        self::assertSame(5000, $dashboard['wonValue']);
    }

    public function testDashboardExportsCsv(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $response = $client->request('GET', '/api/v1/dashboard/export', ['auth_bearer' => $token]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('text/csv', $response->getHeaders()['content-type'][0] ?? '');
        self::assertStringContainsString('Pistes contactées', $response->getContent());
    }

    public function testRatesPipelineAndSegmentsFromPlayedScenario(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        // Édition : gagnée (contact → réponse → gagnée). A/V : contactée sans réponse.
        // Technique : perdue APRÈS discussion. Une 4e piste jamais contactée.
        $wonLead = $this->aLead($client, $token, 'Éditions du Nord', 'PUBLISHING');
        $this->post($client, $token, "/api/v1/leads/$wonLead/contact");
        $this->post($client, $token, "/api/v1/leads/$wonLead/reply");
        $this->post($client, $token, "/api/v1/leads/$wonLead/win");

        $silentLead = $this->aLead($client, $token, 'Studio Sub', 'AUDIOVISUAL');
        $this->post($client, $token, "/api/v1/leads/$silentLead/contact");

        $lostLead = $this->aLead($client, $token, 'Agence Tech', 'TECHNICAL');
        $this->post($client, $token, "/api/v1/leads/$lostLead/contact");
        $this->post($client, $token, "/api/v1/leads/$lostLead/reply");
        $this->post($client, $token, "/api/v1/leads/$lostLead/lose");

        $this->aLead($client, $token, 'Éditions du Sud', 'PUBLISHING');

        $dashboard = $this->dashboard($client, $token);

        // Taux par piste : 3 contactées, 2 avec réponse, 1 gagnée / 1 perdue.
        self::assertSame(3, $dashboard['contacted']);
        self::assertSame(2, $dashboard['replied']);
        self::assertSame(1, $dashboard['won']);
        self::assertSame(1, $dashboard['lost']);
        // Actives = tout sauf gagnée + perdue (la jamais-contactée et la silencieuse).
        self::assertSame(2, $dashboard['activeLeads']);
        // 3 contacts ce mois-ci (aucune relance dans le scénario).
        self::assertSame(3, $dashboard['outreachThisMonth']);

        // Pipeline : ordre kanban, statuts vides omis.
        self::assertSame(
            [
                ['status' => 'TO_CONTACT', 'count' => 1],
                ['status' => 'CONTACTED', 'count' => 1],
                ['status' => 'WON', 'count' => 1],
                ['status' => 'LOST', 'count' => 1],
            ],
            $dashboard['pipeline'],
        );

        // Semaine courante : 3 actes.
        $acts = array_column($dashboard['weeklyActivity'], 'acts');
        self::assertSame(3, end($acts));

        // Segments : ordre canonique, comptes par piste.
        self::assertSame(
            [
                ['segment' => 'PUBLISHING', 'contacted' => 1, 'replied' => 1, 'won' => 1],
                ['segment' => 'AUDIOVISUAL', 'contacted' => 1, 'replied' => 0, 'won' => 0],
                ['segment' => 'TECHNICAL', 'contacted' => 1, 'replied' => 1, 'won' => 0],
            ],
            $dashboard['segments'],
        );
    }

    public function testHistorySurvivesStatusTransitions(): void
    {
        // Décision n°2 : une piste gagnée reste « contactée » et « répondue » dans les taux.
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $lead = $this->aLead($client, $token, 'Éditions du Nord');
        $this->post($client, $token, "/api/v1/leads/$lead/contact");
        $this->post($client, $token, "/api/v1/leads/$lead/reply");
        $this->post($client, $token, "/api/v1/leads/$lead/win");

        $dashboard = $this->dashboard($client, $token);

        self::assertSame(1, $dashboard['contacted']);
        self::assertSame(1, $dashboard['replied']);
        self::assertSame(1, $dashboard['won']);
        self::assertSame(0, $dashboard['activeLeads']);
    }

    public function testDashboardIsTenantIsolated(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();
        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $tokenB = $this->tokenFor($client, 'b@plume.test');

        $lead = $this->aLead($client, $tokenA, 'Éditions du Nord');
        $this->post($client, $tokenA, "/api/v1/leads/$lead/contact");

        $dashboardB = $this->dashboard($client, $tokenB);

        self::assertSame(0, $dashboardB['contacted']);
        self::assertSame(0, $dashboardB['activeLeads']);
        self::assertSame([], $dashboardB['pipeline']);
        self::assertSame([], $dashboardB['segments']);
    }
}
