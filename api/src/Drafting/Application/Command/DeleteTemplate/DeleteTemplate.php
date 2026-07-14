<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\DeleteTemplate;

use App\Shared\Application\Command\Command;

final class DeleteTemplate implements Command
{
    public function __construct(public readonly string $id)
    {
    }
}
