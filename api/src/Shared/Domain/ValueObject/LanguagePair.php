<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidValue;

/**
 * Paire de langues de travail (source > cible), ex. `en>fr`, `es>fr`.
 * Représentation canonique ASCII `xx>yy` (l'UI affiche « en → fr »).
 */
final class LanguagePair
{
    private function __construct(
        private readonly LanguageCode $source,
        private readonly LanguageCode $target,
    ) {
        if ($source->equals($target)) {
            throw InvalidValue::because('A language pair requires two different languages.');
        }
    }

    public static function of(LanguageCode $source, LanguageCode $target): self
    {
        return new self($source, $target);
    }

    /** @param string $value format canonique `xx>yy` */
    public static function fromString(string $value): self
    {
        $parts = explode('>', trim($value));
        if (2 !== \count($parts)) {
            throw InvalidValue::because(sprintf('Invalid language pair "%s" (expected format "en>fr").', $value));
        }

        return new self(LanguageCode::fromString(trim($parts[0])), LanguageCode::fromString(trim($parts[1])));
    }

    public function source(): LanguageCode
    {
        return $this->source;
    }

    public function target(): LanguageCode
    {
        return $this->target;
    }

    public function toString(): string
    {
        return $this->source->toString().'>'.$this->target->toString();
    }

    public function equals(self $other): bool
    {
        return $this->toString() === $other->toString();
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
