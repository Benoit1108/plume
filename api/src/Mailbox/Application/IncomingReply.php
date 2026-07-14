<?php

declare(strict_types=1);

namespace App\Mailbox\Application;

/** Réponse entrante rattachée à une piste — extrait TEXTE court, jamais de HTML. */
final class IncomingReply
{
    public function __construct(
        public readonly string $leadId,
        public readonly string $threadKey,
        public readonly string $textPreview,
    ) {
    }
}
