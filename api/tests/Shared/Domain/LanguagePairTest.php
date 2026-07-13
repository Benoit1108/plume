<?php

declare(strict_types=1);

namespace App\Tests\Shared\Domain;

use App\Shared\Domain\Exception\InvalidValue;
use App\Shared\Domain\ValueObject\LanguagePair;
use PHPUnit\Framework\TestCase;

final class LanguagePairTest extends TestCase
{
    public function testParsesAndNormalizesCanonicalForm(): void
    {
        $pair = LanguagePair::fromString(' EN > fr ');

        self::assertSame('en>fr', $pair->toString());
        self::assertSame('en', $pair->source()->toString());
        self::assertSame('fr', $pair->target()->toString());
    }

    public function testEquality(): void
    {
        self::assertTrue(LanguagePair::fromString('es>fr')->equals(LanguagePair::fromString('ES>FR')));
        self::assertFalse(LanguagePair::fromString('es>fr')->equals(LanguagePair::fromString('en>fr')));
    }

    public function testRejectsIdenticalLanguages(): void
    {
        $this->expectException(InvalidValue::class);
        LanguagePair::fromString('fr>fr');
    }

    public function testRejectsMalformedValue(): void
    {
        $this->expectException(InvalidValue::class);
        LanguagePair::fromString('enfr');
    }

    public function testRejectsInvalidLanguageCode(): void
    {
        $this->expectException(InvalidValue::class);
        LanguagePair::fromString('eng>fr');
    }
}
