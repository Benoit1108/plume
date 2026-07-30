<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Infrastructure\Fetcher\OutlookReplyFetcher;
use App\Tests\Support\FakeAccessTokenMinter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** ACL Graph (relève) : seuls les messages d'AUTRUI comptent, aperçu texte nettoyé et borné. */
final class OutlookReplyFetcherTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    private function json(array $payload): MockResponse
    {
        return new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }

    /** @return array<string, mixed> */
    private static function message(string $preview, string $from): array
    {
        return ['from' => ['emailAddress' => ['address' => $from]], 'bodyPreview' => $preview];
    }

    public function testKeepsOnlyForeignMessagesAndCleansPreview(): void
    {
        /** @var list<array<string, mixed>> $queries */
        $queries = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$queries): MockResponse {
            $queries[] = \is_array($options['query'] ?? null) ? $options['query'] : [];

            return $this->json(['value' => [
                self::message('Mon propre message', 'marie@outlook.example'),
                self::message('Bonjour, avec plaisir. Obtenez Outlook pour iOS', 'jeanne@editions.example'),
            ]]);
        });
        $fetcher = new OutlookReplyFetcher($client, new FakeAccessTokenMinter());

        $replies = $fetcher->fetch('refresh', 'marie@outlook.example', ['conv-1' => 'lead-1']);

        self::assertCount(1, $replies);
        self::assertSame('lead-1', $replies[0]->leadId);
        self::assertSame('conv-1', $replies[0]->threadKey);
        // Le nettoyeur coupe la signature mobile « Obtenez Outlook ».
        self::assertSame('Bonjour, avec plaisir.', $replies[0]->textPreview);
        // Le fil est ciblé par conversationId.
        $filter = $queries[0]['$filter'] ?? null;
        self::assertIsString($filter);
        self::assertStringContainsString("conversationId eq 'conv-1'", $filter);
    }

    public function testThreadWithoutForeignMessageYieldsNothing(): void
    {
        $client = new MockHttpClient([
            $this->json(['value' => [self::message('Moi encore', 'marie@outlook.example')]]),
        ]);
        $fetcher = new OutlookReplyFetcher($client, new FakeAccessTokenMinter());

        self::assertSame([], $fetcher->fetch('refresh', 'marie@outlook.example', ['conv-1' => 'lead-1']));
    }

    public function testDeletedThreadIsSkippedQuietly(): void
    {
        $client = new MockHttpClient([
            new MockResponse('{"error":"itemNotFound"}', ['http_code' => 404]),
        ]);
        $fetcher = new OutlookReplyFetcher($client, new FakeAccessTokenMinter());

        self::assertSame([], $fetcher->fetch('refresh', 'marie@outlook.example', ['conv-x' => 'lead-1']));
    }
}
