<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Sender;

use App\Mailbox\Application\MailSender;
use App\Mailbox\Application\MailSenderRegistry;

/** Route vers l'expéditeur du fournisseur (factice sans identifiants). */
final class ProviderMailSenderRegistry implements MailSenderRegistry
{
    public function __construct(
        private readonly FakeMailSender $fake,
        private readonly GmailMailSender $gmail,
        private readonly OutlookMailSender $outlook,
        private readonly string $googleClientId,
        private readonly string $microsoftClientId,
    ) {
    }

    public function senderFor(string $provider): MailSender
    {
        return match ($provider) {
            'GMAIL' => '' === trim($this->googleClientId) ? $this->fake : $this->gmail,
            'OUTLOOK' => '' === trim($this->microsoftClientId) ? $this->fake : $this->outlook,
            default => $this->fake,
        };
    }
}
