<?php

declare(strict_types=1);

namespace App\Shared\Application;

/** Port de génération d'identifiants (UUID v7 en Infrastructure). */
interface IdGenerator
{
    public function generate(): string;
}
