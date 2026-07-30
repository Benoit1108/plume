<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Application\OutgoingMail;
use App\Mailbox\Infrastructure\Sender\OutlookMailSender;
use App\Tests\Support\FakeAccessTokenMinter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * ACL Graph (envoi) : brouillon créé puis envoyé, conversationId renvoyé comme threadKey, une
 * relance repart via createReply DANS le fil d'origine, échecs propres.
 */
final class OutlookMailSenderTest extends TestCase
{
    /** @param array<string, mixed> $payload */
    private function json(array $payload): MockResponse
    {
        return new MockResponse(json_encode($payload, \JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }

    public function testCreatesDraftThenSendsAndReturnsConversationId(): void
    {
        /** @var list<array{url: string, body: string}> $calls */
        $calls = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls): MockResponse {
            $calls[] = ['url' => $url, 'body' => \is_string($options['body'] ?? null) ? $options['body'] : ''];

            // La création du brouillon renvoie les ids ; l'appel /send ne renvoie rien d'utile.
            return str_contains($url, '/send') ? new MockResponse('', ['http_code' => 202]) : $this->json(['id' => 'msg-1', 'conversationId' => 'conv-42']);
        });
        $sender = new OutlookMailSender($client, new FakeAccessTokenMinter());

        $threadKey = $sender->send('refresh', 'marie@outlook.example', new OutgoingMail('jeanne@editions.example', 'Jeanne', 'Candidature', 'Bonjour.'));

        self::assertSame('conv-42', $threadKey);
        // 1) POST création sur /me/messages (nouveau message, PAS createReply) ; 2) POST /send.
        self::assertStringEndsWith('/me/messages', $calls[0]['url']);
        self::assertStringNotContainsString('createReply', $calls[0]['url']);
        self::assertStringContainsString('"subject":"Candidature"', $calls[0]['body']);
        self::assertStringContainsString('"contentType":"Text"', $calls[0]['body']);
        self::assertStringContainsString('"address":"jeanne@editions.example"', $calls[0]['body']);
        self::assertStringContainsString('/messages/msg-1/send', $calls[1]['url']);
    }

    public function testFollowUpUsesCreateReplyOnOriginThread(): void
    {
        /** @var list<array{url: string, body: string}> $calls */
        $calls = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls): MockResponse {
            $calls[] = ['url' => $url, 'body' => \is_string($options['body'] ?? null) ? $options['body'] : ''];

            return str_contains($url, '/send') ? new MockResponse('', ['http_code' => 202]) : $this->json(['id' => 'reply-9', 'conversationId' => 'conv-origine']);
        });
        $sender = new OutlookMailSender($client, new FakeAccessTokenMinter());

        $threadKey = $sender->send('refresh', 'marie@outlook.example', new OutgoingMail('jeanne@editions.example', 'Jeanne', 'Re', 'Relance.', 'conv-origine'));

        self::assertSame('conv-origine', $threadKey);
        // La relance passe par createReply sur le message du fil d'origine, corps sous 'message'.
        self::assertStringContainsString('/messages/conv-origine/createReply', $calls[0]['url']);
        self::assertStringContainsString('"message":{', $calls[0]['body']);
        self::assertStringContainsString('"subject":"Re"', $calls[0]['body']);
        // C'est le NOUVEAU brouillon (reply-9) qui est envoyé, pas le fil.
        self::assertStringContainsString('/messages/reply-9/send', $calls[1]['url']);
    }

    public function testTokenMintFailureIsAMailSendFailure(): void
    {
        $sender = new OutlookMailSender(new MockHttpClient(), new FakeAccessTokenMinter(fails: true));

        $this->expectException(MailSendFailed::class);
        $sender->send('revoked', 'marie@outlook.example', new OutgoingMail('to@x.example', null, null, 'corps'));
    }

    public function testMissingIdsIsAMailSendFailure(): void
    {
        $sender = new OutlookMailSender(new MockHttpClient($this->json(['id' => 'msg-1'])), new FakeAccessTokenMinter());

        $this->expectException(MailSendFailed::class);
        $sender->send('refresh', 'marie@outlook.example', new OutgoingMail('to@x.example', null, null, 'corps'));
    }
}
