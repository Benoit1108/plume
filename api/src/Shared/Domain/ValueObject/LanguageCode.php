<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/** Code langue ISO 639-1 (ex. fr, en, es). */
final class LanguageCode
{
    private function __construct(private readonly string $value)
    {
        if (1 !== preg_match('/^[a-z]{2}$/', $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid ISO 639-1 language code "%s".', $value));
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtolower($value));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
