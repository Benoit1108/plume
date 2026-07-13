<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

/** Code pays ISO 3166-1 alpha-2 (ex. FR, GB, ES). */
final class CountryCode
{
    private function __construct(private readonly string $value)
    {
        if (1 !== preg_match('/^[A-Z]{2}$/', $value)) {
            throw new \InvalidArgumentException(sprintf('Invalid ISO 3166-1 alpha-2 country code "%s".', $value));
        }
    }

    public static function fromString(string $value): self
    {
        return new self(strtoupper($value));
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
