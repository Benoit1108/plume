<?php

declare(strict_types=1);

namespace App\Shared\Application;

/** Port horloge : les handlers ne lisent jamais l'heure système directement (testabilité). */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
