<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Sender;

use App\Mailbox\Application\MailSender;
use App\Mailbox\Application\OutgoingMail;

/** Sélection par l'env : GOOGLE_CLIENT_ID présent → Gmail réel, absent → factice. */
final class MailSenderSelector implements MailSender
{
    public function __construct(
        private readonly FakeMailSender $fake,
        private readonly GmailMailSender $gmail,
        private readonly string $clientId,
    ) {
    }

    public function send(string $refreshTokenPlain, string $fromEmail, OutgoingMail $mail): string
    {
        $delegate = '' === trim($this->clientId) ? $this->fake : $this->gmail;

        return $delegate->send($refreshTokenPlain, $fromEmail, $mail);
    }
}
