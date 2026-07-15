<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Sender;

use App\Mailbox\Application\Exception\MailSendFailed;
use App\Mailbox\Application\MailSender;
use App\Mailbox\Application\OutgoingMail;
use App\Mailbox\Infrastructure\Token\AccessTokenMinter;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * ACL Gmail (envoi). Un access token FRAIS est minté à chaque envoi depuis le
 * refresh token — l'access token stocké n'est jamais utilisé ici. Texte brut
 * (D4), en-têtes UTF-8, renvoie l'id de FIL Gmail (threadId) comme threadKey.
 */
final class GmailMailSender implements MailSender
{
    private const string SEND_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AccessTokenMinter $tokenMinter,
    ) {
    }

    public function send(string $refreshTokenPlain, string $fromEmail, OutgoingMail $mail): string
    {
        $accessToken = $this->tokenMinter->mint($refreshTokenPlain);

        $to = null !== $mail->toName
            ? sprintf('=?UTF-8?B?%s?= <%s>', base64_encode($mail->toName), $mail->toEmail)
            : $mail->toEmail;
        $headers = [
            'From: '.$fromEmail,
            'To: '.$to,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];
        if (null !== $mail->subject && '' !== $mail->subject) {
            $headers[] = sprintf('Subject: =?UTF-8?B?%s?=', base64_encode($mail->subject));
        }
        $raw = implode("\r\n", $headers)."\r\n\r\n".base64_encode($mail->body);

        try {
            $payload = $this->httpClient->request('POST', self::SEND_ENDPOINT, [
                'headers' => ['Authorization' => 'Bearer '.$accessToken],
                'json' => array_filter([
                    'raw' => rtrim(strtr(base64_encode($raw), '+/', '-_'), '='),
                    // Relance : Gmail rattache le message au fil d'origine.
                    'threadId' => $mail->threadKey,
                ], static fn (?string $v): bool => null !== $v),
                'timeout' => 30,
            ])->toArray();
        } catch (ExceptionInterface $e) {
            throw MailSendFailed::because('Gmail send failed.', $e);
        }

        $threadId = $payload['threadId'] ?? null;
        if (!\is_string($threadId) || '' === $threadId) {
            throw MailSendFailed::because('Gmail response has no thread id.');
        }

        return $threadId;
    }
}
