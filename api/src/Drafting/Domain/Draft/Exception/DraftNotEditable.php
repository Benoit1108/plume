<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft\Exception;

use App\Drafting\Domain\Draft\DraftStatus;
use App\Shared\Domain\Exception\Conflict;

/** Draft-first : on n'édite (ni ne copie) qu'un brouillon prêt. */
final class DraftNotEditable extends Conflict
{
    public static function inStatus(DraftStatus $status): self
    {
        return new self(sprintf('Draft cannot be edited while %s.', $status->value));
    }
}
