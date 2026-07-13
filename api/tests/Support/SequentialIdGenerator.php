<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Shared\Application\IdGenerator;

final class SequentialIdGenerator implements IdGenerator
{
    private int $next = 1;

    public function generate(): string
    {
        return sprintf('id-%d', $this->next++);
    }
}
