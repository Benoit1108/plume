<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Exception;

/**
 * L'envoi n'a pas abouti (token révoqué, quota, panne provider). Non `final` : {@see MailboxReauthRequired}
 * en dérive pour le cas « reconnexion requise » (capté à l'identique par tout `catch (MailSendFailed)`).
 */
class MailSendFailed extends \RuntimeException
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
