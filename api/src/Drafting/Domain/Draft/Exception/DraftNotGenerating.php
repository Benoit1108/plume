<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft\Exception;

use App\Drafting\Domain\Draft\DraftStatus;
use App\Shared\Domain\Exception\Conflict;

/**
 * Résultat de génération reçu pour un brouillon qui n'en attend pas
 * (redélivrance Messenger, complete/fail retardataire) : le contenu
 * courant — potentiellement déjà relu — ne doit JAMAIS être écrasé.
 */
final class DraftNotGenerating extends Conflict
{
    public static function inStatus(DraftStatus $status): self
    {
        return new self(sprintf('Draft is not awaiting generation (status "%s").', $status->value));
    }
}
