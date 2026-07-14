<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/**
 * Port de relève : lit UNIQUEMENT les fils que l'app a initiés (minimisation,
 * ADR-0017) et rend les réponses d'autrui — jamais nos propres messages.
 */
interface ReplyFetcher
{
    /**
     * @param array<string, string> $openThreads threadKey => leadId
     *
     * @return IncomingReply[]
     */
    public function fetch(string $refreshTokenPlain, string $ownEmail, array $openThreads): array;
}
