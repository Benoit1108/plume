<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Infrastructure\OAuth\OAuthStateCodec;
use App\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class OAuthStateCodecTest extends TestCase
{
    /** Mémoire des states consommés — partagée quand plusieurs codecs simulent le même service. */
    private static function codec(string $secret, string $now, ?AdapterInterface $used = null): OAuthStateCodec
    {
        return new OAuthStateCodec($secret, new FixedClock(new \DateTimeImmutable($now)), $used ?? new ArrayAdapter());
    }

    public function testValidStateRoundTrip(): void
    {
        $codec = self::codec('secret', '2026-07-14 15:00:00');

        $state = $codec->issue('tenant-1', 'GMAIL');

        self::assertTrue($codec->isValidFor($state, 'tenant-1'));
    }

    public function testStateIsBoundToItsTenant(): void
    {
        $codec = self::codec('secret', '2026-07-14 15:00:00');

        $state = $codec->issue('tenant-1', 'GMAIL');

        // Le state d'un tenant ne connecte JAMAIS la boîte d'un autre (anti-CSRF).
        self::assertFalse($codec->isValidFor($state, 'tenant-2'));
    }

    public function testProviderIsReadBackFromAValidState(): void
    {
        $codec = self::codec('secret', '2026-07-14 15:00:00');

        self::assertSame('OUTLOOK', $codec->providerFrom($codec->issue('tenant-1', 'OUTLOOK')));
        self::assertSame('GMAIL', $codec->providerFrom($codec->issue('tenant-1', 'GMAIL')));
    }

    public function testTamperedStateYieldsNoProvider(): void
    {
        $codec = self::codec('secret', '2026-07-14 15:00:00');

        self::assertNull($codec->providerFrom('garbage'));
        $other = self::codec('autre-secret', '2026-07-14 15:00:00');
        self::assertNull($codec->providerFrom($other->issue('tenant-1', 'OUTLOOK')));
    }

    public function testExpiredStateIsRejected(): void
    {
        $issuedAt = self::codec('secret', '2026-07-14 15:00:00');
        $state = $issuedAt->issue('tenant-1', 'GMAIL');

        $later = self::codec('secret', '2026-07-14 15:11:00');
        self::assertFalse($later->isValidFor($state, 'tenant-1'));
    }

    public function testTamperedStateIsRejected(): void
    {
        $codec = self::codec('secret', '2026-07-14 15:00:00');
        $state = $codec->issue('tenant-1', 'GMAIL');

        self::assertFalse($codec->isValidFor(substr($state, 0, -4).'AAAA', 'tenant-1'));
        self::assertFalse($codec->isValidFor('garbage', 'tenant-1'));
        // Signé avec un autre secret : rejeté.
        $other = self::codec('autre-secret', '2026-07-14 15:00:00');
        self::assertFalse($codec->isValidFor($other->issue('tenant-1', 'GMAIL'), 'tenant-1'));
    }

    /**
     * Revue P3 : un state signé restait REJOUABLE pendant ses 10 minutes. `consume` le brûle —
     * le second passage est refusé comme s'il était invalide.
     */
    public function testStateIsSingleUse(): void
    {
        $used = new ArrayAdapter();
        $codec = self::codec('secret', '2026-07-14 15:00:00', $used);
        $state = $codec->issue('tenant-1', 'GMAIL');

        self::assertTrue($codec->consume($state, 'tenant-1'));
        self::assertFalse($codec->consume($state, 'tenant-1'), 'un state consommé ne doit plus passer');
        self::assertFalse($codec->isValidFor($state, 'tenant-1'));
    }

    public function testConsumeRefusesAnInvalidStateWithoutBurningAnything(): void
    {
        $used = new ArrayAdapter();
        $codec = self::codec('secret', '2026-07-14 15:00:00', $used);

        self::assertFalse($codec->consume('garbage', 'tenant-1'));
        self::assertFalse($codec->consume($codec->issue('tenant-1', 'GMAIL'), 'tenant-2')); // autre tenant

        // Le state légitime du tenant-1 reste utilisable : rien n'a été brûlé à tort.
        $state = $codec->issue('tenant-1', 'GMAIL');
        self::assertTrue($codec->consume($state, 'tenant-1'));
    }
}
