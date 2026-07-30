<?php

declare(strict_types=1);

namespace App\Tests\Prospecting\Application;

use App\Prospecting\Application\ReadModel\DashboardPeriod;
use PHPUnit\Framework\TestCase;

/** La fenêtre du tableau de bord : parsing tolérant + borne basse glissante. */
final class DashboardPeriodTest extends TestCase
{
    public function testUnknownOrMissingValueFallsBackToAll(): void
    {
        self::assertSame(DashboardPeriod::ALL, DashboardPeriod::fromString(null));
        self::assertSame(DashboardPeriod::ALL, DashboardPeriod::fromString(''));
        self::assertSame(DashboardPeriod::ALL, DashboardPeriod::fromString('n-importe-quoi'));
        self::assertSame(DashboardPeriod::LAST_30_DAYS, DashboardPeriod::fromString('30d'));
        self::assertSame(DashboardPeriod::LAST_12_MONTHS, DashboardPeriod::fromString('12m'));
    }

    public function testAllHasNoLowerBound(): void
    {
        $now = new \DateTimeImmutable('2026-07-30 12:00:00', new \DateTimeZone('UTC'));

        self::assertNull(DashboardPeriod::ALL->since($now));
    }

    public function testWindowsSlideFromNow(): void
    {
        $now = new \DateTimeImmutable('2026-07-30 12:00:00', new \DateTimeZone('UTC'));

        self::assertSame('2026-06-30 12:00:00', DashboardPeriod::LAST_30_DAYS->since($now)?->format('Y-m-d H:i:s'));
        self::assertSame('2026-05-01 12:00:00', DashboardPeriod::LAST_90_DAYS->since($now)?->format('Y-m-d H:i:s'));
        self::assertSame('2025-07-30 12:00:00', DashboardPeriod::LAST_12_MONTHS->since($now)?->format('Y-m-d H:i:s'));
    }
}
