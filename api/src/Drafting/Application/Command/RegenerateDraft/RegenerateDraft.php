<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\RegenerateDraft;

use App\Shared\Application\Command\Command;

final class RegenerateDraft implements Command
{
    public function __construct(public readonly string $draftId)
    {
    }
}
