<?php

declare(strict_types=1);

namespace App\Tests\Account\Infrastructure;

use App\Account\Application\Crypto\SecretCipherFailure;
use App\Account\Infrastructure\Crypto\SodiumSecretCipher;
use PHPUnit\Framework\TestCase;

final class SodiumSecretCipherTest extends TestCase
{
    public function testRoundTripHidesThePlaintext(): void
    {
        $cipher = new SodiumSecretCipher(base64_encode(random_bytes(32)), 'app-secret', 'prod');

        $ciphertext = $cipher->encrypt('JBSWY3DPEHPK3PXP');

        self::assertNotSame('JBSWY3DPEHPK3PXP', $ciphertext);
        self::assertStringNotContainsString('JBSWY3DPEHPK3PXP', $ciphertext);
        self::assertSame('JBSWY3DPEHPK3PXP', $cipher->decrypt($ciphertext));
    }

    public function testNonceMakesEveryCiphertextUnique(): void
    {
        $cipher = new SodiumSecretCipher(base64_encode(random_bytes(32)), 'app-secret', 'prod');

        self::assertNotSame($cipher->encrypt('same'), $cipher->encrypt('same'));
    }

    public function testEmptyKeyIsDerivedOutsideProduction(): void
    {
        $cipher = new SodiumSecretCipher('', 'app-secret', 'dev');

        self::assertSame('secret', $cipher->decrypt($cipher->encrypt('secret')));
    }

    public function testDerivedKeyIsSeparatedFromMailboxDomain(): void
    {
        // Même APP_SECRET, mais séparation de domaine (préfixe `totp:`) : la clé TOTP ne coïncide
        // pas avec celle du mailbox. Un chiffré mailbox ne doit donc PAS se déchiffrer ici.
        $totp = new SodiumSecretCipher('', 'shared-app-secret', 'dev');
        $mailboxLike = new \App\Mailbox\Infrastructure\Crypto\SodiumTokenCipher('', 'shared-app-secret', 'dev');

        $this->expectException(SecretCipherFailure::class);
        $totp->decrypt($mailboxLike->encrypt('cross'));
    }

    public function testEmptyKeyIsFatalInProduction(): void
    {
        $this->expectException(\LogicException::class);
        new SodiumSecretCipher('', 'app-secret', 'prod');
    }

    public function testMalformedKeyIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        new SodiumSecretCipher('trop-court', 'app-secret', 'prod');
    }

    public function testWrongKeyFailsClosed(): void
    {
        $one = new SodiumSecretCipher(base64_encode(random_bytes(32)), 's', 'prod');
        $two = new SodiumSecretCipher(base64_encode(random_bytes(32)), 's', 'prod');

        $this->expectException(SecretCipherFailure::class);
        $two->decrypt($one->encrypt('secret'));
    }
}
