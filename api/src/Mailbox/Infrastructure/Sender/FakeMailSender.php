<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Sender;

use App\Mailbox\Application\MailSender;
use App\Mailbox\Application\OutgoingMail;

/**
 * Envoi factice — défaut sans GOOGLE_CLIENT_ID (dev, tests, CI, E2E) :
 * rien ne part nulle part, le threadKey est déterministe.
 */
final class FakeMailSender implements MailSender
{
    public function send(string $refreshTokenPlain, string $fromEmail, OutgoingMail $mail): string
    {
        // Une relance reste dans son fil ; un premier envoi en ouvre un.
        return $mail->threadKey ?? 'fake-thread-'.substr(hash('sha256', $mail->toEmail.'|'.$mail->body), 0, 16);
    }
}
