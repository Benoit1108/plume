<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Infrastructure\Fetcher\GmailReplyFetcher;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** ACL Gmail (relève) : seuls les messages d'AUTRUI comptent, snippet texte borné. */
final class GmailReplyFetcherTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    private function json(array $payload): MockResponse
    {
        return new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }

    /** @return array<string, mixed> */
    private static function message(string $snippet, string $from): array
    {
        return ['snippet' => $snippet, 'payload' => ['headers' => [['name' => 'From', 'value' => $from]]]];
    }

    public function testKeepsOnlyForeignMessagesAndDecodesSnippet(): void
    {
        $client = new MockHttpClient([
            $this->json(['access_token' => 'fresh']),
            $this->json(['messages' => [
                self::message('Mon propre message', 'Marie <marie@gmail.example>'),
                self::message('Merci &amp; à bientôt', 'Jeanne <jeanne@editions.example>'),
            ]]),
        ]);
        $fetcher = new GmailReplyFetcher($client, 'id', 'secret');

        $replies = $fetcher->fetch('refresh', 'marie@gmail.example', ['thread-1' => 'lead-1']);

        self::assertCount(1, $replies);
        self::assertSame('lead-1', $replies[0]->leadId);
        self::assertSame('Merci & à bientôt', $replies[0]->textPreview); // entités décodées, texte pur
    }

    public function testThreadWithoutForeignMessageYieldsNothing(): void
    {
        $client = new MockHttpClient([
            $this->json(['access_token' => 'fresh']),
            $this->json(['messages' => [self::message('Moi encore', 'marie@gmail.example')]]),
        ]);
        $fetcher = new GmailReplyFetcher($client, 'id', 'secret');

        self::assertSame([], $fetcher->fetch('refresh', 'marie@gmail.example', ['thread-1' => 'lead-1']));
    }

    public function testDeletedThreadIsSkippedQuietly(): void
    {
        $client = new MockHttpClient([
            $this->json(['access_token' => 'fresh']),
            new MockResponse('{"error":"notFound"}', ['http_code' => 404]),
        ]);
        $fetcher = new GmailReplyFetcher($client, 'id', 'secret');

        self::assertSame([], $fetcher->fetch('refresh', 'marie@gmail.example', ['thread-x' => 'lead-1']));
    }
}
