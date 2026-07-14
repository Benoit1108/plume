<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/** Résultat d'un échange de code OAuth — tokens EN CLAIR, à chiffrer immédiatement. */
final class OAuthTokens
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly string $emailAddress,
    ) {
    }
}
