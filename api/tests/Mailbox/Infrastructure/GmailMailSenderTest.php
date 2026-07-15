<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Application\OutgoingMail;
use App\Mailbox\Infrastructure\Sender\GmailMailSender;
use App\Tests\Support\FakeAccessTokenMinter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** ACL Gmail (envoi) : appel /messages/send, threadId récupéré, threading relance, échecs propres. */
final class GmailMailSenderTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    private function json(array $payload): MockResponse
    {
        return new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }

    public function testSendsAndReturnsThreadId(): void
    {
        /** @var list<array<string, mixed>> $sendPayloads */
        $sendPayloads = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sendPayloads): MockResponse {
            $body = json_decode(\is_string($options['body'] ?? null) ? $options['body'] : '{}', true);
            $sendPayloads[] = \is_array($body) ? $body : [];

            return $this->json(['id' => 'msg-1', 'threadId' => 'thread-77']);
        });
        $sender = new GmailMailSender($client, new FakeAccessTokenMinter());

        $threadKey = $sender->send('refresh', 'marie@gmail.example', new OutgoingMail('jeanne@editions.example', 'Jeanne', 'Candidature', 'Bonjour.'));

        self::assertSame('thread-77', $threadKey);
        // Premier envoi : pas de threadId dans la requête (nouveau fil).
        self::assertArrayNotHasKey('threadId', $sendPayloads[0]);
    }

    public function testFollowUpCarriesTheOriginThreadId(): void
    {
        /** @var list<array<string, mixed>> $sendPayloads */
        $sendPayloads = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$sendPayloads): MockResponse {
            $body = json_decode(\is_string($options['body'] ?? null) ? $options['body'] : '{}', true);
            $sendPayloads[] = \is_array($body) ? $body : [];

            return $this->json(['id' => 'msg-2', 'threadId' => 'thread-origine']);
        });
        $sender = new GmailMailSender($client, new FakeAccessTokenMinter());

        $sender->send('refresh', 'marie@gmail.example', new OutgoingMail('jeanne@editions.example', 'Jeanne', 'Re', 'Relance.', 'thread-origine'));

        self::assertSame('thread-origine', $sendPayloads[0]['threadId']);
    }

    public function testTokenMintFailureIsAMailSendFailure(): void
    {
        $sender = new GmailMailSender(new MockHttpClient(), new FakeAccessTokenMinter(fails: true));

        $this->expectException(MailSendFailed::class);
        $sender->send('revoked', 'marie@gmail.example', new OutgoingMail('to@x.example', null, null, 'corps'));
    }

    public function testMissingThreadIdIsAMailSendFailure(): void
    {
        $sender = new GmailMailSender(new MockHttpClient($this->json(['id' => 'msg-1'])), new FakeAccessTokenMinter());

        $this->expectException(MailSendFailed::class);
        $sender->send('refresh', 'marie@gmail.example', new OutgoingMail('to@x.example', null, null, 'corps'));
    }
}
