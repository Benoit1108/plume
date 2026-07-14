<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Application\OutgoingMail;
use App\Mailbox\Infrastructure\Sender\GmailMailSender;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** ACL Gmail : access token frais à chaque envoi, threadId récupéré, échecs propres. */
final class GmailMailSenderTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    private function json(array $payload): MockResponse
    {
        return new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }

    public function testMintsFreshTokenThenSendsAndReturnsThreadId(): void
    {
        $requests = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$requests) {
            $requests[] = $url;
            if (str_contains($url, 'oauth2')) {
                return $this->json(['access_token' => 'fresh-token']);
            }

            return $this->json(['id' => 'msg-1', 'threadId' => 'thread-77']);
        });
        $sender = new GmailMailSender($client, 'client-id', 'client-secret');

        $threadKey = $sender->send('refresh-plain', 'marie@gmail.example', new OutgoingMail('jeanne@editions.example', 'Jeanne', 'Candidature', 'Bonjour.'));

        self::assertSame('thread-77', $threadKey);
        self::assertCount(2, $requests);
        self::assertStringContainsString('oauth2.googleapis.com/token', $requests[0]);
        self::assertStringContainsString('messages/send', $requests[1]);
    }

    public function testRefreshFailureIsAMailSendFailure(): void
    {
        $client = new MockHttpClient(new MockResponse('{"error":"invalid_grant"}', ['http_code' => 400]));
        $sender = new GmailMailSender($client, 'id', 'secret');

        $this->expectException(MailSendFailed::class);
        $sender->send('revoked-refresh', 'marie@gmail.example', new OutgoingMail('to@x.example', null, null, 'corps'));
    }

    public function testMissingThreadIdIsAMailSendFailure(): void
    {
        $client = new MockHttpClient([
            $this->json(['access_token' => 'fresh']),
            $this->json(['id' => 'msg-1']),
        ]);
        $sender = new GmailMailSender($client, 'id', 'secret');

        $this->expectException(MailSendFailed::class);
        $sender->send('refresh', 'marie@gmail.example', new OutgoingMail('to@x.example', null, null, 'corps'));
    }
}
