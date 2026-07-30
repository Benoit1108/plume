<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\FetchedAlertEmail;
use App\Mailbox\Infrastructure\Fetcher\OutlookAlertEmailFetcher;
use App\Mailbox\Infrastructure\Token\AccessTokenMinter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * ACL Graph (relève d'alertes) : aucun réseau réel — MockHttpClient rejoue résolution du dossier
 * (mailFolders) → liste des messages. Vérifie l'extraction From/Subject/corps (texte inline via
 * `Prefer`), le repli sur bodyPreview, et la résilience best-effort (dossier absent / liste en
 * erreur / message sans id).
 */
final class OutlookAlertEmailFetcherTest extends TestCase
{
    private const string LABEL = 'Plume/Alertes';

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

        return (new OutlookAlertEmailFetcher($client, $minter))->fetch('refresh-plain', self::LABEL);
    }

    private function json(mixed $data): MockResponse
    {
        return new MockResponse((string) json_encode($data), ['response_headers' => ['content-type' => 'application/json']]);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function message(string $id, array $overrides = []): array
    {
        return array_merge([
            'id' => $id,
            'from' => ['emailAddress' => ['address' => 'jobs-noreply@linkedin.com']],
            'subject' => 'Traducteur EN>FR — sous-titrage',
            'body' => ['contentType' => 'text', 'content' => "Une offre EN>FR\nhttps://example.test/job/1"],
            'bodyPreview' => 'Une offre EN>FR',
        ], $overrides);
    }

    public function testReadsFolderMessages(): void
    {
        // '/messages' AVANT 'mailFolders' : une URL de liste contient les deux, on veut la liste.
        $alerts = $this->fetchWith([
            '/messages' => $this->json(['value' => [
                self::message('AAA'),
                self::message('BBB', [
                    'from' => ['emailAddress' => ['address' => 'no-reply@proz.com']],
                    'subject' => 'New job posted',
                    'body' => ['contentType' => 'text', 'content' => 'Corps texte ProZ'],
                ]),
            ]]),
            'mailFolders' => $this->json(['value' => [['id' => 'folder-1']]]),
        ]);

        self::assertCount(2, $alerts);
        self::assertSame('jobs-noreply@linkedin.com', $alerts[0]->fromAddress);
        self::assertSame('Traducteur EN>FR — sous-titrage', $alerts[0]->subject);
        self::assertStringContainsString('Une offre EN>FR', $alerts[0]->body);
        self::assertSame('AAA', $alerts[0]->externalId);

        self::assertSame('no-reply@proz.com', $alerts[1]->fromAddress);
        self::assertSame('Corps texte ProZ', $alerts[1]->body);
        self::assertSame('BBB', $alerts[1]->externalId);
    }

    public function testFallsBackToBodyPreviewWhenNoContent(): void
    {
        $alerts = $this->fetchWith([
            '/messages' => $this->json(['value' => [
                self::message('AAA', ['body' => ['contentType' => 'text', 'content' => '   ']]),
            ]]),
            'mailFolders' => $this->json(['value' => [['id' => 'folder-1']]]),
        ]);

        self::assertCount(1, $alerts);
        self::assertSame('Une offre EN>FR', $alerts[0]->body); // repli sur bodyPreview
    }

    public function testReturnsEmptyWhenFolderAbsent(): void
    {
        $alerts = $this->fetchWith([
            'mailFolders' => $this->json(['value' => []]),
        ]);

        self::assertSame([], $alerts);
    }

    public function testSwallowsMessageListFailure(): void
    {
        $alerts = $this->fetchWith([
            '/messages' => new MockResponse('boom', ['http_code' => 500]),
            'mailFolders' => $this->json(['value' => [['id' => 'folder-1']]]),
        ]);

        self::assertSame([], $alerts);
    }

    public function testSkipsMessageWithoutIdButKeepsOthers(): void
    {
        $alerts = $this->fetchWith([
            '/messages' => $this->json(['value' => [
                ['from' => ['emailAddress' => ['address' => 'x@y.test']], 'subject' => 'sans id'],
                self::message('BBB'),
            ]]),
            'mailFolders' => $this->json(['value' => [['id' => 'folder-1']]]),
        ]);

        self::assertCount(1, $alerts);
        self::assertSame('BBB', $alerts[0]->externalId);
    }
}
