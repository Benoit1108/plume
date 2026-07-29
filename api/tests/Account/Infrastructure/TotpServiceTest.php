<?php

declare(strict_types=1);

namespace App\Tests\Account\Infrastructure;

use App\Account\Infrastructure\Security\TotpService;
use PHPUnit\Framework\TestCase;

/**
 * Le wrapper TOTP (RFC 6238 via otphp) : round-trip, tolérance de fenêtre ±1 pas, rejet des codes
 * mal formés, codes de secours (entropie, hash normalisé). Le temps est injecté (`$now`) → déterministe.
 */
final class TotpServiceTest extends TestCase
{
    private TotpService $totp;
    private string $secret;

    protected function setUp(): void
    {
        $this->totp = new TotpService();
        $this->secret = $this->totp->generateSecret();
    }

    public function testGeneratedSecretIsUsableBase32(): void
    {
        self::assertMatchesRegularExpression('/^[A-Z2-7]+$/', $this->secret);
    }

    public function testCurrentCodeVerifiesAndReturnsItsStep(): void
    {
        $now = 1_785_000_000;
        $code = $this->totp->currentCode($this->secret, $now);

        self::assertSame(intdiv($now, TotpService::PERIOD), $this->totp->verify($this->secret, $code, $now));
    }

    public function testAcceptsAdjacentStepButNotBeyondWindow(): void
    {
        $now = 1_785_000_000;
        $code = $this->totp->currentCode($this->secret, $now);

        // ±1 pas (dérive d'horloge) : accepté.
        self::assertNotNull($this->totp->verify($this->secret, $code, $now + TotpService::PERIOD));
        self::assertNotNull($this->totp->verify($this->secret, $code, $now - TotpService::PERIOD));
        // Au-delà de la fenêtre : refusé.
        self::assertNull($this->totp->verify($this->secret, $code, $now + 3 * TotpService::PERIOD));
    }

    public function testRejectsMalformedCodes(): void
    {
        $now = 1_785_000_000;
        self::assertNull($this->totp->verify($this->secret, '12345', $now));      // 5 chiffres
        self::assertNull($this->totp->verify($this->secret, 'abcdef', $now));      // non numérique
        self::assertNull($this->totp->verify($this->secret, '', $now));
        self::assertNull($this->totp->verify($this->secret, '000000', $now));      // presque sûrement faux
    }

    public function testProvisioningUriIsOtpauth(): void
    {
        $uri = $this->totp->provisioningUri($this->secret, 'marie@plume.test');
        self::assertStringStartsWith('otpauth://totp/', $uri);
        self::assertStringContainsString('issuer=Plume', $uri);
    }

    public function testBackupCodesEntropyAndHashing(): void
    {
        $codes = $this->totp->generateBackupCodes();

        self::assertCount(8, $codes['plain']);
        self::assertCount(8, $codes['hashed']);
        foreach ($codes['plain'] as $i => $plain) {
            self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{8}$/', $plain); // 64 bits
            self::assertSame(TotpService::hashBackupCode($plain), $codes['hashed'][$i]);
        }
        self::assertSame($codes['plain'][0], array_unique($codes['plain'])[0] ?? null); // pas de doublon en tête
        self::assertCount(8, array_unique($codes['plain']));
        // Hash normalisé (casse/espaces).
        self::assertSame(TotpService::hashBackupCode('AB12-CD34'), TotpService::hashBackupCode('  ab12-cd34 '));
    }
}
