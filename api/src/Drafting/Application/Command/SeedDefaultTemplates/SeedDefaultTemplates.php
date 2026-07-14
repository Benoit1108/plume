<?php

declare(strict_types=1);

namespace App\Drafting\Application\Command\SeedDefaultTemplates;

use App\Shared\Application\Command\Command;

final class SeedDefaultTemplates implements Command
{
    public function __construct(public readonly string $tenantId)
    {
    }
}
