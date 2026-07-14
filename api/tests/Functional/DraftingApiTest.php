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
 * Tests fonctionnels M1.4 : brouillons (génération canned synchrone en env test,
 * édition, régénération, suppression, garde RGPD), gabarits (seed + CRUD),
 * profil étendu, journal draft_generated, isolation tenant.
 */
final class DraftingApiTest extends ApiTestCase
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

    /** @param array<string, mixed> $json */
    private function patch(Client $client, string $token, string $url, array $json): \Symfony\Contracts\HttpClient\ResponseInterface
    {
        return $client->request('PATCH', $url, [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => $json,
        ]);
    }

    private function anOrganization(Client $client, string $token, string $name): string
    {
        $response = $this->post($client, $token, '/api/v1/organizations', [
            'name' => $name,
            'type' => 'PUBLISHER',
            'contacts' => [['fullName' => 'Jeanne Duval', 'role' => 'Éditrice']],
        ]);
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
     * @param array<string, mixed> $overrides
     *
     * @return array{id: string, leadId: string, status: string, subject: ?string, body: string}
     */
    private function generateDraft(Client $client, string $token, string $leadId, array $overrides = []): array
    {
        $response = $this->post($client, $token, sprintf('/api/v1/leads/%s/drafts', $leadId), $overrides + [
            'type' => 'APPLICATION_EMAIL',
            'targetLanguage' => 'fr',
        ]);
        self::assertResponseStatusCodeSame(201);

        /** @var array{id: string, leadId: string, status: string, subject: ?string, body: string} $draft */
        $draft = $response->toArray();

        return $draft;
    }

    /**
     * Membres d'une collection (format JSON-LD).
     *
     * @return list<array<string, mixed>>
     */
    private function members(Client $client, string $token, string $url): array
    {
        $collection = $client->request('GET', $url, ['auth_bearer' => $token])->toArray();

        /** @var list<array<string, mixed>> $members */
        $members = $collection['member'] ?? [];

        return $members;
    }

    public function testGeneratesReadyDraftWithProfileAndJournalEntry(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $this->patch($client, $token, '/api/v1/profile', [
            'bio' => 'Traductrice EN>FR spécialisée en littérature.',
            'signature' => 'Marie Untel',
        ]);
        $leadId = $this->aLead($client, $token, $this->anOrganization($client, $token, 'Éditions du Nord'));

        // Transport sync en test : la génération canned aboutit dans la requête.
        $draft = $this->generateDraft($client, $token, $leadId);

        self::assertSame('READY', $draft['status']);
        self::assertSame($leadId, $draft['leadId']);
        self::assertNotNull($draft['subject']);
        self::assertStringContainsString('Éditions du Nord', $draft['body']);
        self::assertStringContainsString('Traductrice EN>FR spécialisée', $draft['body']);
        self::assertStringContainsString('Marie Untel', $draft['body']);

        // Liste des brouillons de la piste + journal draft_generated.
        self::assertCount(1, $this->members($client, $token, sprintf('/api/v1/leads/%s/drafts', $leadId)));

        $timeline = $this->members($client, $token, sprintf('/api/v1/leads/%s/interactions', $leadId));
        self::assertContains('draft_generated', array_column($timeline, 'type'));
    }

    public function testDraftUsesSeededTemplate(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $leadId = $this->aLead($client, $token, $this->anOrganization($client, $token, 'Éditions du Sud'));

        $templates = $this->members($client, $token, '/api/v1/templates');
        $seeded = array_values(array_filter($templates, static fn (array $t): bool => 'Candidature édition (FR)' === ($t['name'] ?? null)));
        self::assertNotEmpty($seeded);
        $templateId = $seeded[0]['id'];
        self::assertIsString($templateId);

        $draft = $this->generateDraft($client, $token, $leadId, ['templateId' => $templateId]);

        self::assertSame('READY', $draft['status']);
        self::assertStringContainsString('Éditions du Sud', $draft['body']);
        self::assertStringContainsString('EN → FR', (string) $draft['subject']);
    }

    public function testGenerationForbiddenWhenOrganizationIsDoNotContact(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $orgId = $this->anOrganization($client, $token, 'Studio Muet');
        $leadId = $this->aLead($client, $token, $orgId);

        // RGPD d'abord : la cible passe doNotContact APRÈS la création de la piste.
        $this->patch($client, $token, sprintf('/api/v1/organizations/%s', $orgId), ['doNotContact' => true]);
        self::assertResponseIsSuccessful();

        $this->post($client, $token, sprintf('/api/v1/leads/%s/drafts', $leadId), [
            'type' => 'APPLICATION_EMAIL',
            'targetLanguage' => 'fr',
        ]);
        self::assertResponseStatusCodeSame(409);
    }

    public function testEditRegenerateAndDeleteDraft(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $leadId = $this->aLead($client, $token, $this->anOrganization($client, $token, 'Éditions du Nord'));
        $draft = $this->generateDraft($client, $token, $leadId);
        $draftId = $draft['id'];

        // Relecture humaine : édition du sujet et du corps.
        $edited = $this->patch($client, $token, sprintf('/api/v1/drafts/%s', $draftId), [
            'subject' => 'Mon sujet relu',
            'body' => 'Corps entièrement réécrit.',
        ])->toArray();
        self::assertSame('Mon sujet relu', $edited['subject'] ?? null);
        self::assertSame('Corps entièrement réécrit.', $edited['body'] ?? null);

        // Régénération : le contenu édité est remplacé par une nouvelle génération.
        $regenerated = $this->post($client, $token, sprintf('/api/v1/drafts/%s/regenerate', $draftId))->toArray();
        self::assertSame('READY', $regenerated['status'] ?? null);
        self::assertNotSame('Corps entièrement réécrit.', $regenerated['body'] ?? null);

        $client->request('DELETE', sprintf('/api/v1/drafts/%s', $draftId), ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(204);
        $client->request('GET', sprintf('/api/v1/drafts/%s', $draftId), ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(404);
    }

    public function testTemplatesAreSeededOnceThenCrud(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        self::assertCount(3, $this->members($client, $token, '/api/v1/templates'));

        // Pas de re-seed au second appel.
        self::assertCount(3, $this->members($client, $token, '/api/v1/templates'));

        $created = $this->post($client, $token, '/api/v1/templates', [
            'name' => 'Relance technique (EN)',
            'type' => 'FOLLOW_UP_EMAIL',
            'segment' => 'TECHNICAL',
            'language' => 'en',
            'subject' => 'Follow-up',
            'body' => 'Hello {{contact}}, just checking in. {{signature}}',
        ])->toArray();
        self::assertResponseStatusCodeSame(201);
        $createdId = $created['id'] ?? null;
        self::assertIsString($createdId);

        $updated = $this->patch($client, $token, sprintf('/api/v1/templates/%s', $createdId), [
            'name' => 'Relance technique v2',
        ])->toArray();
        self::assertSame('Relance technique v2', $updated['name'] ?? null);

        $client->request('DELETE', sprintf('/api/v1/templates/%s', $createdId), ['auth_bearer' => $token]);
        self::assertResponseStatusCodeSame(204);

        self::assertCount(3, $this->members($client, $token, '/api/v1/templates'));
    }

    public function testProfilePresentationRoundTrip(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');

        $this->patch($client, $token, '/api/v1/profile', [
            'weeklyGoal' => 7,
            'bio' => 'Bio.',
            'specialties' => 'Édition jeunesse, sous-titrage.',
            'signature' => 'Marie',
        ]);
        self::assertResponseIsSuccessful();

        $profile = $client->request('GET', '/api/v1/profile', ['auth_bearer' => $token])->toArray();
        self::assertSame(7, $profile['weeklyGoal'] ?? null);
        self::assertSame('Bio.', $profile['bio'] ?? null);
        self::assertSame('Édition jeunesse, sous-titrage.', $profile['specialties'] ?? null);
        self::assertSame('Marie', $profile['signature'] ?? null);
    }

    public function testDraftsAndTemplatesAreTenantIsolated(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();
        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $tokenB = $this->tokenFor($client, 'b@plume.test');

        $leadId = $this->aLead($client, $tokenA, $this->anOrganization($client, $tokenA, 'Éditions du Nord'));
        $draft = $this->generateDraft($client, $tokenA, $leadId);

        // Le tenant B ne voit ni le brouillon, ni la liste de la piste de A.
        $client->request('GET', sprintf('/api/v1/drafts/%s', $draft['id']), ['auth_bearer' => $tokenB]);
        self::assertResponseStatusCodeSame(404);
        self::assertCount(0, $this->members($client, $tokenB, sprintf('/api/v1/leads/%s/drafts', $leadId)));

        // Chaque tenant a SES gabarits (seed séparé), pas ceux du voisin.
        $templatesA = $this->members($client, $tokenA, '/api/v1/templates');
        $templatesB = $this->members($client, $tokenB, '/api/v1/templates');
        self::assertCount(3, $templatesA);
        self::assertCount(3, $templatesB);
        /** @var list<string> $idsA */
        $idsA = array_column($templatesA, 'id');
        /** @var list<string> $idsB */
        $idsB = array_column($templatesB, 'id');
        self::assertSame([], array_intersect($idsA, $idsB));

        $client->request('GET', sprintf('/api/v1/templates/%s', $idsA[0]), ['auth_bearer' => $tokenB]);
        self::assertResponseStatusCodeSame(404);
    }
}
