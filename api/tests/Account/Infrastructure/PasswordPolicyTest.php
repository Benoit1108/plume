<?php

declare(strict_types=1);

namespace App\Tests\Account\Infrastructure;

use App\Account\Infrastructure\Security\PasswordPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * La politique est le miroir EXACT de ce que le front affiche en direct
 * (`app/utils/account/passwordPolicy.ts`) : si les deux divergent, l'utilisatrice voit ses
 * quatre règles cochées et se fait quand même refuser — le pire des deux mondes.
 */
final class PasswordPolicyTest extends TestCase
{
    /** @return iterable<string, array{string, list<string>}> */
    public static function passwords(): iterable
    {
        yield 'conforme' => ['Bonjour!23', []];
        yield 'accents et tiret' => ['Éléphant-rose', []];
        yield 'trop court' => ['Ab!c', ['too_short']];
        yield 'sans majuscule' => ['bonjour!23', ['missing_uppercase']];
        yield 'sans minuscule' => ['BONJOUR!23', ['missing_lowercase']];
        yield 'sans caractère spécial' => ['Bonjour123', ['missing_special']];
        yield 'vide' => ['', ['too_short', 'missing_lowercase', 'missing_uppercase', 'missing_special']];
        yield 'chiffres seuls' => ['12345678', ['missing_lowercase', 'missing_uppercase', 'missing_special']];
    }

    /** @param list<string> $expected */
    #[DataProvider('passwords')]
    public function testReportsEveryUnmetRule(string $password, array $expected): void
    {
        self::assertSame($expected, PasswordPolicy::violations($password));
        self::assertSame([] === $expected, PasswordPolicy::isSatisfiedBy($password));
    }

    public function testRejectsAnUnreasonablyLongPassword(): void
    {
        $violations = PasswordPolicy::violations('Aa!'.str_repeat('x', PasswordPolicy::MAX_LENGTH));
        self::assertContains('too_long', $violations);
    }

    /** Une phrase de passe longue et simple reste acceptée si elle tient les quatre règles. */
    public function testAcceptsALongPassphrase(): void
    {
        self::assertTrue(PasswordPolicy::isSatisfiedBy('Le chat dort sur le clavier !'));
    }
}
