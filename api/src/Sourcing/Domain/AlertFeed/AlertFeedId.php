<?php

declare(strict_types=1);

namespace App\Sourcing\Domain\AlertFeed;

use App\Shared\Domain\Exception\InvalidValue;

final class AlertFeedId
{
    private function __construct(private readonly string $value)
    {
        if ('' === $value) {
            throw InvalidValue::because('AlertFeedId cannot be empty.');
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
