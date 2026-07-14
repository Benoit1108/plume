<?php

declare(strict_types=1);

namespace App\Mailbox\Infrastructure\Fetcher;

use App\Mailbox\Application\IncomingReply;
use App\Mailbox\Application\ReplyFetcher;

/**
 * Relève factice (défaut sans GOOGLE_CLIENT_ID) : chaque fil ouvert « fake »
 * reçoit une réponse déterministe — la boucle complète se joue sans compte réel.
 */
final class FakeReplyFetcher implements ReplyFetcher
{
    public function fetch(string $refreshTokenPlain, string $ownEmail, array $openThreads): array
    {
        $replies = [];
        foreach ($openThreads as $threadKey => $leadId) {
            if (str_starts_with($threadKey, 'fake-thread-')) {
                $replies[] = new IncomingReply($leadId, $threadKey, 'Merci pour votre message, pouvez-vous nous envoyer vos références ?');
            }
        }

        return $replies;
    }
}
