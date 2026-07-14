<?php

declare(strict_types=1);

namespace App\Tests\Mailbox\Infrastructure;

use App\Mailbox\Application\Exception\TokenCipherFailure;
use App\Mailbox\Infrastructure\Crypto\SodiumTokenCipher;
use PHPUnit\Framework\TestCase;

final class SodiumTokenCipherTest extends TestCase
{
    public function testRoundTripWithExplicitKey(): void
    {
        $cipher = new SodiumTokenCipher(base64_encode(random_bytes(32)), 'app-secret', 'prod');

        $ciphertext = $cipher->encrypt('ya29.secret-token');

        self::assertNotSame('ya29.secret-token', $ciphertext);
        self::assertStringNotContainsString('secret-token', $ciphertext);
        self::assertSame('ya29.secret-token', $cipher->decrypt($ciphertext));
    }

    public function testNonceMakesEveryCiphertextUnique(): void
    {
        $cipher = new SodiumTokenCipher(base64_encode(random_bytes(32)), 'app-secret', 'prod');

        self::assertNotSame($cipher->encrypt('same'), $cipher->encrypt('same'));
    }

    public function testEmptyKeyIsDerivedOutsideProduction(): void
    {
        $cipher = new SodiumTokenCipher('', 'app-secret', 'dev');

        self::assertSame('tok', $cipher->decrypt($cipher->encrypt('tok')));
    }

    public function testEmptyKeyIsFatalInProduction(): void
    {
        $this->expectException(\LogicException::class);
        new SodiumTokenCipher('', 'app-secret', 'prod');
    }

    public function testMalformedKeyIsRejected(): void
    {
        $this->expectException(\LogicException::class);
        new SodiumTokenCipher('trop-court', 'app-secret', 'prod');
    }

    public function testWrongKeyFailsClosed(): void
    {
        $one = new SodiumTokenCipher(base64_encode(random_bytes(32)), 's', 'prod');
        $two = new SodiumTokenCipher(base64_encode(random_bytes(32)), 's', 'prod');
        $ciphertext = $one->encrypt('tok');

        $this->expectException(TokenCipherFailure::class);
        $two->decrypt($ciphertext);
    }
}
