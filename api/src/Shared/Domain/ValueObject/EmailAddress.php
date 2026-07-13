<?php

declare(strict_types=1);

namespace App\Shared\Domain\ValueObject;

use App\Shared\Domain\Exception\InvalidValue;

final class EmailAddress
{
    private function __construct(private readonly string $value)
    {
        if (false === filter_var($value, \FILTER_VALIDATE_EMAIL)) {
            throw InvalidValue::because(sprintf('Invalid email address "%s".', $value));
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return strtolower($this->value) === strtolower($other->value);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
