<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Infrastructure\OAuth\OAuthStateCodec;
use App\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class OAuthStateCodecTest extends TestCase
{
    public function testValidStateRoundTrip(): void
    {
        $codec = new OAuthStateCodec('secret', new FixedClock(new \DateTimeImmutable('2026-07-14 15:00:00')));

        $state = $codec->issue('tenant-1');

        self::assertTrue($codec->isValidFor($state, 'tenant-1'));
    }

    public function testStateIsBoundToItsTenant(): void
    {
        $codec = new OAuthStateCodec('secret', new FixedClock(new \DateTimeImmutable('2026-07-14 15:00:00')));

        $state = $codec->issue('tenant-1');

        // Le state d'un tenant ne connecte JAMAIS la boîte d'un autre (anti-CSRF).
        self::assertFalse($codec->isValidFor($state, 'tenant-2'));
    }

    public function testExpiredStateIsRejected(): void
    {
        $issuedAt = new OAuthStateCodec('secret', new FixedClock(new \DateTimeImmutable('2026-07-14 15:00:00')));
        $state = $issuedAt->issue('tenant-1');

        $later = new OAuthStateCodec('secret', new FixedClock(new \DateTimeImmutable('2026-07-14 15:11:00')));
        self::assertFalse($later->isValidFor($state, 'tenant-1'));
    }

    public function testTamperedStateIsRejected(): void
    {
        $codec = new OAuthStateCodec('secret', new FixedClock(new \DateTimeImmutable('2026-07-14 15:00:00')));
        $state = $codec->issue('tenant-1');

        self::assertFalse($codec->isValidFor(substr($state, 0, -4).'AAAA', 'tenant-1'));
        self::assertFalse($codec->isValidFor('garbage', 'tenant-1'));
        // Signé avec un autre secret : rejeté.
        $other = new OAuthStateCodec('autre-secret', new FixedClock(new \DateTimeImmutable('2026-07-14 15:00:00')));
        self::assertFalse($codec->isValidFor($other->issue('tenant-1'), 'tenant-1'));
    }
}
