<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft\Exception;

use App\Shared\Domain\Exception\Conflict;

/** Garde RGPD : pas de génération pour une organisation « ne pas contacter ». */
final class DraftingNotAllowed extends Conflict
{
    public static function doNotContact(): self
    {
        return new self('Drafting refused: the organization is marked do-not-contact.');
    }
}
