<?php

declare(strict_types=1);

namespace App\Tests\Account\Infrastructure;

use App\Account\Infrastructure\Security\EmailVerificationSigner;
use App\Tests\Support\FixedClock;
use PHPUnit\Framework\TestCase;

/**
 * Le jeton de vérification d'email (HMAC sans état) : round-trip, EXPIRATION (24 h), rejet des
 * jetons altérés/mal formés. Sans ces tests, retirer le contrôle d'expiration passerait la CI.
 */
final class EmailVerificationSignerTest extends TestCase
{
    private const SECRET = 'test-app-secret';

    private function signerAt(string $when): EmailVerificationSigner
    {
        return new EmailVerificationSigner(new FixedClock(new \DateTimeImmutable($when)), self::SECRET);
    }

    public function testRoundTripReturnsEmail(): void
    {
        $token = $this->signerAt('2026-07-29 10:00:00')->sign('marie@plume.test');

        self::assertSame('marie@plume.test', $this->signerAt('2026-07-29 10:30:00')->verify($token));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $token = $this->signerAt('2026-07-29 10:00:00')->sign('marie@plume.test');

        // 25 h plus tard : au-delà du TTL de 24 h.
        self::assertNull($this->signerAt('2026-07-30 11:00:00')->verify($token));
    }

    public function testTamperedTokenIsRejected(): void
    {
        $signer = $this->signerAt('2026-07-29 10:00:00');
        $token = $signer->sign('marie@plume.test');

        self::assertNull($signer->verify($token.'x'));                       // signature altérée
        self::assertNull($signer->verify('garbage'));                        // pas de séparateur
        self::assertNull($signer->verify('Zm9v.deadbeef'));                  // payload/hmac incohérents
        self::assertNull($signer->verify(''));
    }

    public function testDifferentSecretCannotVerify(): void
    {
        $token = $this->signerAt('2026-07-29 10:00:00')->sign('marie@plume.test');
        $other = new EmailVerificationSigner(new FixedClock(new \DateTimeImmutable('2026-07-29 10:30:00')), 'another-secret');

        self::assertNull($other->verify($token));
    }
}
