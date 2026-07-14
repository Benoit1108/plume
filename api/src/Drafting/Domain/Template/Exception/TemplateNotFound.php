<?php

declare(strict_types=1);

namespace App\Drafting\Domain\Template\Exception;

use App\Drafting\Domain\Template\TemplateId;
use App\Shared\Domain\Exception\NotFound;

final class TemplateNotFound extends NotFound
{
    public static function withId(TemplateId $id): self
    {
        return new self(sprintf('Template "%s" not found.', $id->toString()));
    }
}
