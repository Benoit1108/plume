<?php

declare(strict_types=1);

namespace App\Mailbox\Application\Exception;

/**
 * Le refresh token n'est plus valable côté fournisseur (`invalid_grant` : consentement révoqué,
 * expiré, mot de passe changé…) : rafraîchir ne servira à rien, seule une RECONNEXION OAuth relance
 * la boîte. Distinct d'un incident transitoire (réseau/5xx → {@see MailSendFailed}). Étend
 * MailSendFailed pour que tout `catch (MailSendFailed)` existant (chemin d'envoi) le capte à
 * l'identique — seuls les appelants qui veulent DISTINGUER la reconnexion le catchent en premier.
 */
final class MailboxReauthRequired extends MailSendFailed
{
    public static function reauthRequired(?\Throwable $previous = null): self
    {
        return new self('OAuth refresh token invalid — reconnection required.', 0, $previous);
    }
}
