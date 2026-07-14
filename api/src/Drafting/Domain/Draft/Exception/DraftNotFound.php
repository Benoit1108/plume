<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Draft\Exception;

use App\Drafting\Domain\Draft\DraftId;
use App\Shared\Domain\Exception\NotFound;

final class DraftNotFound extends NotFound
{
    public static function withId(DraftId $id): self
    {
        return new self(sprintf('Draft "%s" not found.', $id->toString()));
    }
}
