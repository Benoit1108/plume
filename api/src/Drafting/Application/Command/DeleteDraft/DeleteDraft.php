<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\DeleteDraft;

use App\Shared\Application\Command\Command;

final class DeleteDraft implements Command
{
    public function __construct(public readonly string $draftId)
    {
    }
}
