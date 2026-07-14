<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

use App\Mailbox\Application\Exception\MailSendFailed;

/**
 * Port d'envoi. L'adaptateur mint un access token FRAIS depuis le refresh
 * token (déchiffré en mémoire par l'appelant) à chaque envoi : simple,
 * et l'access token stocké reste un détail sans valeur.
 */
interface MailSender
{
    /**
     * @return string threadKey (Message-ID/threadId provider) pour capter les réponses
     *
     * @throws MailSendFailed
     */
    public function send(string $refreshTokenPlain, string $fromEmail, OutgoingMail $mail): string;
}
