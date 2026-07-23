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
 * Tests fonctionnels du pipeline Lead contre Postgres : cycle de vie complet,
 * transitions interdites (409), unicité de piste active, garde RGPD, ISOLATION
 * TENANT, et journal d'interactions (events consommés en ligne en env test).
 */
final class LeadApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE interaction, lead, organization, app_user, refresh_tokens RESTART IDENTITY CASCADE');
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

    private function anOrganization(Client $client, string $token, string $name = 'Actes Sud'): string
    {
        $response = $this->post($client, $token, '/api/v1/organizations', ['name' => $name, 'type' => 'PUBLISHER']);
        self::assertResponseStatusCodeSame(201);

        /** @var array{id: string} $org */
        $org = $response->toArray();

        return $org['id'];
    }

    /** @param array<string, mixed> $overrides */
    private function aLead(Client $client, string $token, string $orgId, array $overrides = []): string
    {
        $response = $this->post($client, $token, '/api/v1/leads', $overrides + [
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

    public function testLeadLifecycleToWonWithTimeline(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $orgId = $this->anOrganization($client, $token);

        $leadId = $this->aLead($client, $token, $orgId);
        self::assertJsonContains(['status' => 'TO_CONTACT', 'organizationName' => 'Actes Sud']);

        // Transitions du parcours nominal — chaque réponse porte les actions suivantes.
        $response = $this->post($client, $token, "/api/v1/leads/$leadId/contact");
        self::assertResponseIsSuccessful();
        /** @var array{allowedActions: string[]} $contacted */
        $contacted = $response->toArray();
        self::assertContains('reply', $contacted['allowedActions']);

        $this->post($client, $token, "/api/v1/leads/$leadId/reply");
        self::assertResponseIsSuccessful();
        $this->post($client, $token, "/api/v1/leads/$leadId/sample-test");
        self::assertResponseIsSuccessful();
        $response = $this->post($client, $token, "/api/v1/leads/$leadId/win");
        self::assertResponseIsSuccessful();
        self::assertSame('WON', $response->toArray()['status']);
        self::assertSame([], $response->toArray()['allowedActions']);

        // Note manuelle.
        $this->post($client, $token, "/api/v1/leads/$leadId/notes", ['text' => 'Contrat signé !']);
        self::assertResponseStatusCodeSame(201);

        // Journal projeté (transport sync en test) : 6 entrées, la note en premier (plus récent).
        $response = $client->request('GET', "/api/v1/leads/$leadId/interactions", [
            'auth_bearer' => $token,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);
        self::assertResponseIsSuccessful();
        $collection = $response->toArray();
        /** @var list<array{type: string, payload: array<string, mixed>}> $items */
        $items = $collection['member'] ?? $collection['hydra:member'] ?? [];
        self::assertCount(8, $items);
        // La cadence vit avec le pipeline : relance auto au contact, annulée à la réponse.
        self::assertSame(
            ['note', 'won', 'sample_test', 'follow_up_cancelled', 'reply', 'follow_up_scheduled', 'contacted', 'created'],
            array_column($items, 'type'),
        );
        self::assertSame('Contrat signé !', $items[0]['payload']['text'] ?? null);
    }

    public function testReturnToContactMovesLeadBack(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $leadId = $this->aLead($client, $token, $this->anOrganization($client, $token));

        // Contacter (par erreur) → CONTACTED, l'action de retour est proposée.
        $this->post($client, $token, "/api/v1/leads/$leadId/contact");
        /** @var array{status: string, allowedActions: string[]} $contacted */
        $contacted = $client->request('GET', "/api/v1/leads/$leadId", ['auth_bearer' => $token])->toArray();
        self::assertSame('CONTACTED', $contacted['status']);
        self::assertContains('back-to-contact', $contacted['allowedActions']);

        // Repasser à « À contacter » : retour TO_CONTACT + date de contact effacée
        // (une valeur null n'est pas sérialisée → la clé disparaît de la réponse).
        /** @var array{status: string} $back */
        $back = $this->post($client, $token, "/api/v1/leads/$leadId/back-to-contact")->toArray();
        self::assertSame('TO_CONTACT', $back['status']);
        self::assertArrayNotHasKey('lastContactedAt', $back);
    }

    public function testHasReachableContactReflectsOrganizationContacts(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $orgId = $this->anOrganization($client, $token);
        $leadId = $this->aLead($client, $token, $orgId);

        // Organisation sans contact → drapeau faux (le front confirmera avant « Contacter »).
        $lead = $client->request('GET', "/api/v1/leads/$leadId", ['auth_bearer' => $token])->toArray();
        self::assertFalse($lead['hasReachableContact']);

        // Ajout d'un contact avec email → drapeau vrai.
        $this->post($client, $token, "/api/v1/organizations/$orgId/contacts", ['fullName' => 'Jean Dupont', 'email' => 'jean@actes.example']);
        $withContact = $client->request('GET', "/api/v1/leads/$leadId", ['auth_bearer' => $token])->toArray();
        self::assertTrue($withContact['hasReachableContact']);
    }

    public function testIllegalTransitionIsConflict(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $leadId = $this->aLead($client, $token, $this->anOrganization($client, $token));

        // Gagner sans discussion → 409.
        $this->post($client, $token, "/api/v1/leads/$leadId/win");
        self::assertResponseStatusCodeSame(409);
    }

    public function testOnlyOneActiveLeadPerOrganization(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $orgId = $this->anOrganization($client, $token);

        $this->aLead($client, $token, $orgId);
        $this->post($client, $token, '/api/v1/leads', [
            'organizationId' => $orgId,
            'languagePair' => 'es>fr',
            'source' => 'REFERRAL',
            'priority' => 'LOW',
            'segment' => 'PUBLISHING',
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testDoNotContactOrganizationRefusesLeads(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $orgId = $this->anOrganization($client, $token);
        $leadId = $this->aLead($client, $token, $orgId);

        // L'organisation passe « ne pas contacter » APRÈS la création de la piste.
        $client->request('PATCH', "/api/v1/organizations/$orgId", [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['doNotContact' => true],
        ]);
        self::assertResponseIsSuccessful();

        // La démarcher devient interdit (garde RGPD au niveau commande).
        $this->post($client, $token, "/api/v1/leads/$leadId/contact");
        self::assertResponseStatusCodeSame(409);
    }

    public function testUnknownOrganizationIsUnprocessable(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $this->post($client, $token, '/api/v1/leads', [
            'organizationId' => Uuid::v7()->toRfc4122(),
            'languagePair' => 'en>fr',
            'source' => 'DIRECT',
            'priority' => 'HIGH',
            'segment' => 'PUBLISHING',
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testTenantIsolationOnLeads(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();
        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $tokenB = $this->tokenFor($client, 'b@plume.test');
        $leadId = $this->aLead($client, $tokenA, $this->anOrganization($client, $tokenA));

        // B ne voit rien…
        $response = $client->request('GET', '/api/v1/leads', [
            'auth_bearer' => $tokenB,
            'headers' => ['Accept' => 'application/ld+json'],
        ]);
        $collection = $response->toArray();
        self::assertSame(0, $collection['totalItems'] ?? $collection['hydra:totalItems'] ?? null);

        // …ni la piste, ni sa timeline, ni ses transitions.
        $client->request('GET', "/api/v1/leads/$leadId", ['auth_bearer' => $tokenB]);
        self::assertResponseStatusCodeSame(404);

        $this->post($client, $tokenB, "/api/v1/leads/$leadId/contact");
        self::assertResponseStatusCodeSame(404);
    }
}
