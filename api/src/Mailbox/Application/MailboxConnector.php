<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

use App\Mailbox\Application\Exception\MailboxConnectionFailed;

/**
 * Port OAuth d'un fournisseur de boîte (Gmail d'abord — D1). L'échange de code
 * et la révocation se font CÔTÉ SERVEUR (le client_secret ne quitte jamais l'API).
 */
interface MailboxConnector
{
    /** URL de consentement où envoyer le navigateur (state anti-CSRF inclus). */
    public function authorizationUrl(string $state): string;

    /** @throws MailboxConnectionFailed code invalide/expiré, refus, panne provider */
    public function exchangeCode(string $code): OAuthTokens;

    /** Révoque le consentement côté provider (silencieux si déjà révoqué). */
    public function revoke(string $refreshTokenPlain): void;
}
