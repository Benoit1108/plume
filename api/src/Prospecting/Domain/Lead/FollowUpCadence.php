<?php

declare(strict_types=1);

namespace App\Prospecting\Domain\Lead;

/**
 * Cadence de relance par défaut (décision M1.3 n°1) : J+7, puis J+21, puis J+45
 * après chaque relance faite — au-delà, la planification redevient manuelle.
 */
final class FollowUpCadence
{
    /** @var int[] délais en jours, indexés par nombre de relances déjà faites */
    public const array DEFAULT_DAYS = [7, 21, 45];

    public static function nextDelayInDays(int $followUpsDone): ?int
    {
        return self::DEFAULT_DAYS[$followUpsDone] ?? null;
    }
}
