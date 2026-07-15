<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Infrastructure\Token\OAuthAccessTokenMinter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** Frappe OAuth refresh_token : access token frais, échecs → MailSendFailed. */
final class OAuthAccessTokenMinterTest extends TestCase
{
    public function testMintsAccessTokenFromRefresh(): void
    {
        $capturedUrl = '';
        /** @var array<string, mixed> $capturedBody */
        $capturedBody = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$capturedUrl, &$capturedBody): MockResponse {
            $capturedUrl = $url;
            // HttpClient sérialise le body form en chaîne urlencodée.
            $raw = $options['body'] ?? '';
            if (\is_string($raw)) {
                parse_str($raw, $capturedBody);
            }

            return new MockResponse('{"access_token":"fresh-42"}', ['response_headers' => ['content-type' => 'application/json']]);
        });
        $minter = new OAuthAccessTokenMinter($client, 'https://token.example/oauth', 'cid', 'csecret');

        self::assertSame('fresh-42', $minter->mint('refresh-plain'));
        self::assertStringContainsString('token.example', $capturedUrl);
        self::assertSame('refresh_token', $capturedBody['grant_type']);
        self::assertSame('refresh-plain', $capturedBody['refresh_token']);
    }

    public function testHttpErrorIsAMailSendFailure(): void
    {
        $client = new MockHttpClient(new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400]));
        $minter = new OAuthAccessTokenMinter($client, 'https://token.example/oauth', 'cid', 'csecret');

        $this->expectException(MailSendFailed::class);
        $minter->mint('revoked');
    }

    public function testMissingAccessTokenIsAMailSendFailure(): void
    {
        $client = new MockHttpClient(new MockResponse('{"scope":"x"}', ['response_headers' => ['content-type' => 'application/json']]));
        $minter = new OAuthAccessTokenMinter($client, 'https://token.example/oauth', 'cid', 'csecret');

        $this->expectException(MailSendFailed::class);
        $minter->mint('refresh');
    }
}
