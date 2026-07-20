<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\RawAlert;

interface RawAlertRepository
{
    public function save(RawAlert $rawAlert): void;
}
