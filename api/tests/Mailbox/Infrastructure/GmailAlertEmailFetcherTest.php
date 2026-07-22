<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\FetchedAlertEmail;
use App\Mailbox\Infrastructure\Fetcher\GmailAlertEmailFetcher;
use App\Mailbox\Infrastructure\Token\AccessTokenMinter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * ACL Gmail (relève d'alertes) : aucun réseau réel — MockHttpClient rejoue labels → liste →
 * message. Vérifie la résolution du label, l'extraction From/Subject/corps (base64url Gmail sans
 * padding, y compris multipart), et la résilience best-effort (label absent / message illisible).
 */
final class GmailAlertEmailFetcherTest extends TestCase
{
    private const string LABEL = 'Plume/Alertes';

    /** base64url NON paddé, comme Gmail. */
    private static function b64url(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * @param array<string, MockResponse> $routes clé = fragment d'URL, testé dans l'ordre d'insertion
     *
     * @return FetchedAlertEmail[]
     */
    private function fetchWith(array $routes): array
    {
        $client = new MockHttpClient(function (string $method, string $url) use ($routes): MockResponse {
            foreach ($routes as $fragment => $response) {
                if (str_contains($url, $fragment)) {
                    return $response;
                }
            }

            return new MockResponse('{}', ['http_code' => 404]);
        });

        $minter = $this->createStub(AccessTokenMinter::class);
        $minter->method('mint')->willReturn('access-token-xyz');

        return (new GmailAlertEmailFetcher($client, $minter))->fetch('refresh-plain', self::LABEL);
    }

    private function json(mixed $data): MockResponse
    {
        return new MockResponse((string) json_encode($data), ['response_headers' => ['content-type' => 'application/json']]);
    }

    public function testReadsLabelledMessagesIncludingMultipart(): void
    {
        $alerts = $this->fetchWith([
            '/labels' => $this->json(['labels' => [
                ['id' => 'Label_9', 'name' => self::LABEL],
                ['id' => 'INBOX', 'name' => 'INBOX'],
            ]]),
            '/messages/m1' => $this->json([
                'id' => 'm1',
                'payload' => [
                    'mimeType' => 'text/plain',
                    'headers' => [
                        ['name' => 'From', 'value' => 'jobs-noreply@linkedin.com'],
                        ['name' => 'Subject', 'value' => 'Traducteur EN>FR — sous-titrage'],
                    ],
                    'body' => ['data' => self::b64url("Une offre EN>FR\nhttps://example.test/job/1")],
                ],
            ]),
            '/messages/m2' => $this->json([
                'id' => 'm2',
                'payload' => [
                    'mimeType' => 'multipart/alternative',
                    'headers' => [
                        ['name' => 'From', 'value' => 'no-reply@proz.com'],
                        ['name' => 'Subject', 'value' => 'New job posted'],
                    ],
                    'parts' => [
                        ['mimeType' => 'text/html', 'body' => ['data' => self::b64url('<p>ignore</p>')]],
                        ['mimeType' => 'text/plain', 'body' => ['data' => self::b64url('Corps texte ProZ')]],
                    ],
                ],
            ]),
            '/messages' => $this->json(['messages' => [['id' => 'm1'], ['id' => 'm2']]]),
        ]);

        self::assertCount(2, $alerts);

        self::assertSame('jobs-noreply@linkedin.com', $alerts[0]->fromAddress);
        self::assertSame('Traducteur EN>FR — sous-titrage', $alerts[0]->subject);
        self::assertStringContainsString('Une offre EN>FR', $alerts[0]->body);
        self::assertSame('m1', $alerts[0]->externalId);

        // multipart : on retient la part text/plain (pas le HTML).
        self::assertSame('no-reply@proz.com', $alerts[1]->fromAddress);
        self::assertSame('Corps texte ProZ', $alerts[1]->body);
        self::assertSame('m2', $alerts[1]->externalId);
    }

    public function testReturnsEmptyWhenLabelAbsent(): void
    {
        $alerts = $this->fetchWith([
            '/labels' => $this->json(['labels' => [['id' => 'INBOX', 'name' => 'INBOX']]]),
        ]);

        self::assertSame([], $alerts);
    }

    public function testSwallowsMessageListFailure(): void
    {
        $alerts = $this->fetchWith([
            '/labels' => $this->json(['labels' => [['id' => 'Label_9', 'name' => self::LABEL]]]),
            '/messages' => new MockResponse('boom', ['http_code' => 500]),
        ]);

        self::assertSame([], $alerts);
    }

    public function testSkipsUnreadableMessageButKeepsOthers(): void
    {
        $alerts = $this->fetchWith([
            '/labels' => $this->json(['labels' => [['id' => 'Label_9', 'name' => self::LABEL]]]),
            '/messages/m1' => $this->json([
                'id' => 'm1',
                'payload' => [
                    'mimeType' => 'text/plain',
                    'headers' => [['name' => 'From', 'value' => 'a@b.test'], ['name' => 'Subject', 'value' => 'OK']],
                    'body' => ['data' => self::b64url('corps')],
                ],
            ]),
            '/messages/m2' => new MockResponse('nope', ['http_code' => 500]),
            '/messages' => $this->json(['messages' => [['id' => 'm1'], ['id' => 'm2']]]),
        ]);

        self::assertCount(1, $alerts);
        self::assertSame('m1', $alerts[0]->externalId);
    }
}
