<?php

declare(strict_types=1);

namespace App\Account\Infrastructure\Scheduler;

/** Tick du Scheduler : purger physiquement les comptes en soft-delete au-delà du délai de grâce (RGPD, V2.0-a2). */
final class PurgeDeletedAccountsTick
{
}
