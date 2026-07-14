<?php

declare(strict_types=1);

namespace App\Mailbox\Domain\Outbound\Exception;

use App\Shared\Domain\Exception\Conflict;

/** RGPD : la cible a demandé à ne plus être contactée — l'envoi est refusé. */
final class RecipientNotContactable extends Conflict
{
    public static function create(): self
    {
        return new self('This organization asked not to be contacted.');
    }
}
