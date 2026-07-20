<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Sourcing\Domain\RawAlert\RawAlert;
use App\Sourcing\Domain\RawAlert\RawAlertRepository;

final class InMemoryRawAlertRepository implements RawAlertRepository
{
    /** @var list<RawAlert> */
    public array $saved = [];

    public function save(RawAlert $rawAlert): void
    {
        $this->saved[] = $rawAlert;
    }

    public function count(): int
    {
        return \count($this->saved);
    }
}
