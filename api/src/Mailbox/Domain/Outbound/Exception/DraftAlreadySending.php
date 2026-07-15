<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound\Exception;

use App\Shared\Domain\Exception\Conflict;

/** Un envoi de ce brouillon est déjà en cours ou abouti : pas de double envoi (double-clic, rejeu). */
final class DraftAlreadySending extends Conflict
{
    public static function forDraft(string $draftId): self
    {
        return new self(sprintf('An email is already sending or sent for draft "%s".', $draftId));
    }
}
