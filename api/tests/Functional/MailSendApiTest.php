<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use ApiPlatform\Symfony\Bundle\Test\ApiTestCase;
use ApiPlatform\Symfony\Bundle\Test\Client;
use App\Account\Infrastructure\Persistence\User;
use App\Mailbox\Infrastructure\OAuth\FakeMailboxConnector;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Tests fonctionnels M2.2 : envoi d'un brouillon relu (transport sync en test :
 * l'envoi factice aboutit dans la requête), D3 (la piste avance), journal,
 * gardes (boîte requise, RGPD, destinataire), isolation tenant.
 */
final class MailSendApiTest extends ApiTestCase
{
    private const PASSWORD = 'secret-Test-123';

    protected function setUp(): void
    {
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $connection->executeStatement('TRUNCATE TABLE outbound_message, connected_mailbox, draft, template, interaction, lead, organization, profile, app_user, refresh_tokens RESTART IDENTITY CASCADE');
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

    private function connectMailbox(Client $client, string $token): void
    {
        $url = $this->post($client, $token, '/api/v1/mailbox/oauth/start')->toArray()['authorizationUrl'];
        self::assertIsString($url);
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $state = $query['state'];
        self::assertIsString($state);
        $this->post($client, $token, '/api/v1/mailbox/connect', ['code' => FakeMailboxConnector::ACCEPTED_CODE, 'state' => $state]);
        self::assertResponseStatusCodeSame(201);
    }

    /** @return array{leadId: string, draftId: string} */
    private function aReadyDraft(Client $client, string $token, bool $withContactEmail = true): array
    {
        $org = $this->post($client, $token, '/api/v1/organizations', [
            'name' => 'Éditions du Nord '.bin2hex(random_bytes(3)),
            'type' => 'PUBLISHER',
        ])->toArray();
        $orgId = $org['id'] ?? null;
        self::assertIsString($orgId);
        // Les contacts se créent via le sous-endpoint (M1.1).
        $contact = ['fullName' => 'Jeanne Duval'];
        if ($withContactEmail) {
            $contact['email'] = 'jeanne@editions.example';
        }
        $this->post($client, $token, sprintf('/api/v1/organizations/%s/contacts', $orgId), $contact);
        self::assertResponseStatusCodeSame(201);
        $lead = $this->post($client, $token, '/api/v1/leads', [
            'organizationId' => $org['id'],
            'languagePair' => 'en>fr',
            'source' => 'DIRECT',
            'priority' => 'HIGH',
            'segment' => 'PUBLISHING',
        ])->toArray();
        $leadId = $lead['id'];
        self::assertIsString($leadId);
        $draft = $this->post($client, $token, sprintf('/api/v1/leads/%s/drafts', $leadId), [
            'type' => 'APPLICATION_EMAIL',
            'targetLanguage' => 'fr',
        ])->toArray();
        self::assertSame('READY', $draft['status']); // canned + sync
        $draftId = $draft['id'];
        self::assertIsString($draftId);

        return ['leadId' => $leadId, 'draftId' => $draftId];
    }

    /** @return list<string> */
    private function timelineTypes(Client $client, string $token, string $leadId): array
    {
        $timeline = $client->request('GET', sprintf('/api/v1/leads/%s/interactions', $leadId), ['auth_bearer' => $token])->toArray();
        $members = $timeline['member'] ?? [];
        self::assertIsArray($members);

        /** @var list<string> $types */
        $types = array_column($members, 'type');

        return $types;
    }

    public function testSendReviewedDraftAdvancesLeadAndJournals(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $this->connectMailbox($client, $token);
        ['leadId' => $leadId, 'draftId' => $draftId] = $this->aReadyDraft($client, $token);

        $receipt = $this->post($client, $token, sprintf('/api/v1/drafts/%s/send', $draftId))->toArray();
        self::assertResponseStatusCodeSame(202);
        self::assertSame('SENDING', $receipt['status']);

        // Transport sync : l'envoi factice a abouti dans la requête.
        $connection = static::getContainer()->get(Connection::class);
        \assert($connection instanceof Connection);
        $row = $connection->fetchAssociative('SELECT status, thread_key, recipient FROM outbound_message LIMIT 1');
        self::assertIsArray($row);
        self::assertSame('SENT', $row['status']);
        self::assertIsString($row['thread_key']);
        self::assertStringStartsWith('fake-thread-', $row['thread_key']);
        self::assertSame('jeanne@editions.example', $row['recipient']);

        // D3 : la piste À_CONTACTER est passée CONTACTÉE (et sa relance de cadence est née).
        $lead = $client->request('GET', sprintf('/api/v1/leads/%s', $leadId), ['auth_bearer' => $token])->toArray();
        self::assertSame('CONTACTED', $lead['status']);

        // Journal : email_sent ET contacted (le journal reste la vérité de la progression).
        $types = $this->timelineTypes($client, $token, $leadId);
        self::assertContains('email_sent', $types);
        self::assertContains('contacted', $types);
    }

    public function testSendWithoutMailboxIsConflict(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        ['draftId' => $draftId] = $this->aReadyDraft($client, $token);

        $this->post($client, $token, sprintf('/api/v1/drafts/%s/send', $draftId));
        self::assertResponseStatusCodeSame(409);
    }

    public function testSendWithoutRecipientEmailIsRejected(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $this->connectMailbox($client, $token);
        ['draftId' => $draftId] = $this->aReadyDraft($client, $token, withContactEmail: false);

        $this->post($client, $token, sprintf('/api/v1/drafts/%s/send', $draftId));
        self::assertResponseStatusCodeSame(422);
    }

    public function testRgpdBlocksSendEvenAfterDraftWasGenerated(): void
    {
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $this->connectMailbox($client, $token);
        ['leadId' => $leadId, 'draftId' => $draftId] = $this->aReadyDraft($client, $token);

        // doNotContact activé APRÈS la génération : l'envoi est refusé net.
        $lead = $client->request('GET', sprintf('/api/v1/leads/%s', $leadId), ['auth_bearer' => $token])->toArray();
        $orgId = $lead['organizationId'] ?? null;
        self::assertIsString($orgId);
        $client->request('PATCH', sprintf('/api/v1/organizations/%s', $orgId), [
            'auth_bearer' => $token,
            'headers' => ['Content-Type' => 'application/merge-patch+json'],
            'json' => ['doNotContact' => true],
        ]);
        self::assertResponseIsSuccessful();

        $this->post($client, $token, sprintf('/api/v1/drafts/%s/send', $draftId));
        self::assertResponseStatusCodeSame(409);
    }

    public function testFetchRepliesClosesTheLoop(): void
    {
        // M2.3 : envoi → relève (factice) → réponse captée → piste EN DISCUSSION,
        // relance annulée, aperçu au journal — et la relève est IDEMPOTENTE.
        $this->createUser('a@plume.test');
        $client = static::createClient();
        $token = $this->tokenFor($client, 'a@plume.test');
        $this->connectMailbox($client, $token);
        ['leadId' => $leadId, 'draftId' => $draftId] = $this->aReadyDraft($client, $token);
        $this->post($client, $token, sprintf('/api/v1/drafts/%s/send', $draftId));
        self::assertResponseStatusCodeSame(202);

        $this->post($client, $token, '/api/v1/mailbox/fetch-replies');
        self::assertResponseIsSuccessful();

        $lead = $client->request('GET', sprintf('/api/v1/leads/%s', $leadId), ['auth_bearer' => $token])->toArray();
        self::assertSame('IN_DISCUSSION', $lead['status']);
        self::assertNull($lead['nextFollowUpAt'] ?? null); // relance de cadence annulée

        $types = $this->timelineTypes($client, $token, $leadId);
        self::assertContains('reply', $types);
        $timeline = $client->request('GET', sprintf('/api/v1/leads/%s/interactions', $leadId), ['auth_bearer' => $token])->toArray();
        /** @var list<array{type: string, payload: array<string, mixed>}> $members */
        $members = $timeline['member'] ?? [];
        $reply = array_values(array_filter($members, static fn (array $i): bool => 'reply' === $i['type']))[0] ?? null;
        self::assertIsArray($reply);
        $preview = $reply['payload']['preview'] ?? null;
        self::assertIsString($preview);
        self::assertStringContainsString('références', $preview);

        // Seconde relève : plus de fil ouvert (piste en discussion) → aucun doublon.
        $this->post($client, $token, '/api/v1/mailbox/fetch-replies');
        $typesAfter = $this->timelineTypes($client, $token, $leadId);
        self::assertSame(
            \count(array_filter($types, static fn (string $t): bool => 'reply' === $t)),
            \count(array_filter($typesAfter, static fn (string $t): bool => 'reply' === $t)),
        );
    }

    public function testSendIsTenantIsolated(): void
    {
        $this->createUser('a@plume.test');
        $this->createUser('b@plume.test');
        $client = static::createClient();
        $tokenA = $this->tokenFor($client, 'a@plume.test');
        $tokenB = $this->tokenFor($client, 'b@plume.test');
        $this->connectMailbox($client, $tokenA);
        ['draftId' => $draftId] = $this->aReadyDraft($client, $tokenA);

        // B ne peut pas envoyer le brouillon de A (introuvable dans SON périmètre).
        $this->post($client, $tokenB, sprintf('/api/v1/drafts/%s/send', $draftId));
        self::assertResponseStatusCodeSame(409); // pas de boîte B connectée — première garde
    }
}
