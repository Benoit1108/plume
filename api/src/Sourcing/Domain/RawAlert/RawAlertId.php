<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\RawAlert;

use App\Shared\Domain\Exception\InvalidValue;

final class RawAlertId
{
    private function __construct(private readonly string $value)
    {
        if ('' === $value) {
            throw InvalidValue::because('RawAlertId cannot be empty.');
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

    public function __toString(): string
    {
        return $this->value;
    }
}
