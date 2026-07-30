<?php

declare(strict_types=1);

namespace App\Tests\Prospecting\Domain;

use App\Prospecting\Domain\Lead\FollowUpCadence;
use App\Shared\Domain\Exception\InvalidValue;
use PHPUnit\Framework\TestCase;

/** La séquence de relance : défaut, validation à l'écriture, tolérance à la lecture, progression. */
final class FollowUpCadenceTest extends TestCase
{
    public function testDefaultIsTheHistoricalCadence(): void
    {
        $cadence = FollowUpCadence::default();
        self::assertSame([7, 21, 45], $cadence->days());
        self::assertSame(7, $cadence->nextDelayInDays(0));
        self::assertSame(45, $cadence->nextDelayInDays(2));
        self::assertNull($cadence->nextDelayInDays(3)); // fin de séquence
    }

    public function testEmptyCadenceMeansNoAutoFollowUp(): void
    {
        self::assertNull(FollowUpCadence::fromDays([])->nextDelayInDays(0));
    }

    public function testFromDaysRejectsOutOfRangeDelays(): void
    {
        $this->expectException(InvalidValue::class);
        FollowUpCadence::fromDays([7, 400]); // > 365
    }

    public function testFromDaysRejectsTooManySteps(): void
    {
        $this->expectException(InvalidValue::class);
        FollowUpCadence::fromDays(array_fill(0, 11, 7)); // > 10 étapes
    }

    public function testFromStoredIsTolerantAndFallsBackToDefault(): void
    {
        // Valeurs abîmées filtrées ; si rien d'exploitable → défaut (la planification ne casse jamais).
        self::assertSame([7, 30], FollowUpCadence::fromStoredDays([7, 0, 30, 9999])->days());
        self::assertSame([7, 21, 45], FollowUpCadence::fromStoredDays([])->days());
        self::assertSame([7, 21, 45], FollowUpCadence::fromStoredDays([-1, 0])->days());
    }
}
