<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Token;

use App\Mailbox\Application\Exception\MailSendFailed;

/**
 * Frappe un access token FRAIS depuis le refresh token (OAuth refresh_token
 * grant), partagé par l'expéditeur ET la relève d'un même fournisseur —
 * l'access token stocké n'est jamais réutilisé (surface réduite).
 */
interface AccessTokenMinter
{
    /** @throws MailSendFailed */
    public function mint(string $refreshTokenPlain): string;
}
