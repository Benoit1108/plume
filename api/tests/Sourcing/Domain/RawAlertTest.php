<?php

declare(strict_types=1);

namespace App\Tests\Sourcing\Domain;

use App\Shared\Domain\ValueObject\TenantId;
use App\Sourcing\Domain\CandidateLead\Source;
use App\Sourcing\Domain\RawAlert\RawAlert;
use App\Sourcing\Domain\RawAlert\RawAlertId;
use PHPUnit\Framework\TestCase;

final class RawAlertTest extends TestCase
{
    public function testCaptureKeepsThePayloadVerbatim(): void
    {
        $now = new \DateTimeImmutable('2026-07-20 10:00:00');
        $raw = RawAlert::capture(
            RawAlertId::fromString('raw-1'),
            TenantId::fromString('tenant-1'),
            Source::RSS,
            '<item>brut</item>',
            $now,
        );

        self::assertSame('raw-1', $raw->id()->toString());
        self::assertSame(Source::RSS, $raw->source());
        self::assertSame('<item>brut</item>', $raw->payload());
        self::assertSame($now, $raw->fetchedAt());
    }
}
