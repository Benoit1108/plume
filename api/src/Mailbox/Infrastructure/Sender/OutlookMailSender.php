<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Sender;

use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Application\MailSender;
use App\Mailbox\Application\OutgoingMail;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ACL Graph (envoi). Un brouillon est CRÉÉ (il porte le conversationId, notre
 * threadKey), éventuellement rattaché au fil d'origine pour une relance (reply),
 * puis envoyé. Texte brut (D4), access token frais minté à chaque envoi.
 */
final class OutlookMailSender implements MailSender
{
    private const string TOKEN_ENDPOINT = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
    private const string MESSAGES_ENDPOINT = 'https://graph.microsoft.com/v1.0/me/messages';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $clientId,
        private readonly string $clientSecret,
    ) {
    }

    public function send(string $refreshTokenPlain, string $fromEmail, OutgoingMail $mail): string
    {
        $accessToken = $this->mintAccessToken($refreshTokenPlain);
        $auth = ['Authorization' => 'Bearer '.$accessToken];

        try {
            // Une relance repart dans le fil d'origine (createReply) ; sinon nouveau message.
            $createEndpoint = null !== $mail->threadKey
                ? self::MESSAGES_ENDPOINT.'/'.urlencode($mail->threadKey).'/createReply'
                : self::MESSAGES_ENDPOINT;

            $draftPayload = null !== $mail->threadKey
                ? ['message' => $this->messageBody($mail), 'comment' => '']
                : $this->messageBody($mail);

            $draft = $this->httpClient->request('POST', $createEndpoint, [
                'headers' => $auth,
                'json' => $draftPayload,
                'timeout' => 30,
            ])->toArray();

            $messageId = $draft['id'] ?? null;
            $conversationId = $draft['conversationId'] ?? null;
            if (!\is_string($messageId) || !\is_string($conversationId)) {
                throw MailSendFailed::because('Graph draft creation returned no ids.');
            }

            $this->httpClient->request('POST', self::MESSAGES_ENDPOINT.'/'.urlencode($messageId).'/send', [
                'headers' => $auth,
                'timeout' => 30,
            ])->getStatusCode();
        } catch (ExceptionInterface $e) {
            throw MailSendFailed::because('Graph send failed.', $e);
        }

        return $conversationId;
    }

    /** @return array<string, mixed> */
    private function messageBody(OutgoingMail $mail): array
    {
        return [
            'subject' => $mail->subject ?? '',
            'body' => ['contentType' => 'Text', 'content' => $mail->body],
            'toRecipients' => [['emailAddress' => array_filter([
                'address' => $mail->toEmail,
                'name' => $mail->toName,
            ], static fn (?string $v): bool => null !== $v)]],
        ];
    }

    private function mintAccessToken(string $refreshTokenPlain): string
    {
        try {
            $payload = $this->httpClient->request('POST', self::TOKEN_ENDPOINT, [
                'body' => [
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'refresh_token' => $refreshTokenPlain,
                    'grant_type' => 'refresh_token',
                ],
                'timeout' => 15,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw MailSendFailed::because('Microsoft token refresh failed.', $e);
        }

        $accessToken = $payload['access_token'] ?? null;
        if (!\is_string($accessToken) || '' === $accessToken) {
            throw MailSendFailed::because('Microsoft token refresh returned no access token.');
        }

        return $accessToken;
    }
}
